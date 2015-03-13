<?php

namespace UaComparator\Tool;

use Composer\Script\Event;
use Composer\Package\PackageInterface;
use Composer\IO\IOInterface;
use Browscap\Generator\CollectionParser;
use Browscap\Helper\CollectionCreator;
use Browscap\Helper\Generator;
use Browscap\Writer\Factory\FullCollectionFactory;
use Browscap\Generator\BrowscapJsonGenerator;
use Browscap\Generator\BuildGenerator;
use Browscap\Helper\LoggerHelper;
use Browscap\Data\PropertyHolder;

class ComposerHook
{
    public static function postInstall(Event $event)
    {
        self::postUpdate($event);
    }

    public static function postUpdate(Event $event)
    {
        ini_set('memory_limit', '2048M');
        chdir(dirname(dirname(__DIR__)));

        $installed = $event->getComposer()->getRepositoryManager()->getLocalRepository();

        $requiredPackage = 'browscap/browscap';

        $packages = $installed->findPackages($requiredPackage);

        if (!count($packages)) {
            throw new \Exception("The package {$requiredPackage} does not seem to be installed, and it is required.");
        }

        $package     = reset($packages);
        $buildNumber = self::determineBuildNumberFromPackage($package);

        if (empty($buildNumber)) {
            throw new \Exception("Could not determine build number from package {$requiredPackage}");
        }

        $currentBuildNumber = self::getCurrentBuildNumber();

        if ($buildNumber != $currentBuildNumber) {
            $event->getIO()->write(sprintf('<info>Generating new Browscap build: %s</info>', $buildNumber));
            self::createBuild($buildNumber, $event->getIO());
            $event->getIO()->write(sprintf('<info>All done</info>', $buildNumber));
        } else {
            $event->getIO()->write(sprintf('<info>Current build %s is up to date</info>', $currentBuildNumber));
        }

    }

    /**
     * Try to determine the build number from a composer package
     *
     * @param \Composer\Package\PackageInterface $package
     * @return string
     */
    public static function determineBuildNumberFromPackage(PackageInterface $package)
    {
        if ($package->isDev()) {

            $buildNumber = self::determineBuildNumberFromBrowscapBuildFile();

            if (is_null($buildNumber)) {
               $buildNumber = substr($package->getSourceReference(), 0, 8);
            }
        } else {
            $installedVersion = $package->getPrettyVersion();

            // SemVer supports build numbers, but fall back to just using
            // version number if not available; at time of writing, composer
            // did not support SemVer 2.0.0 build numbers fully:
            // @see https://github.com/composer/composer/issues/2422
            $plusPos = strpos($installedVersion, '+');
            if ($plusPos !== false) {
                $buildNumber = substr($installedVersion, ($plusPos + 1));
            } else {
                $buildNumber = self::determineBuildNumberFromBrowscapBuildFile();

                if (is_null($buildNumber)) {
                   $buildNumber = $installedVersion;
                }
            }
        }

        return $buildNumber;
    }

    /**
     * This is a temporary fallback until Composer supports SemVer 2.0.0 properly
     *
     * @return string|NULL
     */
    public static function determineBuildNumberFromBrowscapBuildFile()
    {
        $buildFile = 'vendor/browscap/browscap/BUILD_NUMBER';

        if (file_exists($buildFile)) {
            $buildNumber = file_get_contents($buildFile);
            return trim($buildNumber);
        } else {
            return null;
        }
    }

    public static function getCurrentBuildNumber()
    {
        $buildFolder  = 'build/';
        $metadataFile = $buildFolder . 'metadata.php';

        if (file_exists($metadataFile)) {
            $metadata = require $metadataFile;
            return $metadata['version'];
        } else {
            return null;
        }
    }

    private static function moveSymlink($buildNumber)
    {
        $buildLink = 'build/build';

        if (file_exists($buildLink) && !is_link($buildLink)) {
            throw new \RuntimeException("Build folder '{$buildLink}' was not a symbolic link");
        } else if (file_exists($buildLink) && is_link($buildLink)) {
            unlink($buildLink);
        }

        $target = realpath($buildLink . '-' . $buildNumber);
        if (!symlink($target, $buildLink)) {
            throw new \RuntimeException("Unable to create symbolic link for target '{$target}'");
        }
    }

    /**
     * Generate a build for build number specified
     *
     * @param string                   $buildNumber
     * @param \Composer\IO\IOInterface $io
     * @param bool                     $debug
     */
    public static function createBuild($buildNumber, IOInterface $io = null, $debug = false)
    {
        $buildFolder    = 'build/build-' . $buildNumber . '/';
        $resourceFolder = 'vendor/browscap/browscap/resources/';

        if (!file_exists($buildFolder)) {
            if ($io) {
                $io->write('  - Creating build folder');
            }
            mkdir($buildFolder, 0775, true);
        }

        // Create a logger
        if ($io) {
            $io->write('  - Setting up logging');
        }

        $loggerHelper = new LoggerHelper();
        $logger       = $loggerHelper->create($debug);

        $collectionCreator = new CollectionCreator();

        if ($io) {
            $io->write('  - Creating writer collection');
        }
        $writerCollectionFactory = new FullCollectionFactory();
        $writerCollection        = $writerCollectionFactory->createCollection($logger, $buildFolder);

        // Generate the actual browscap.ini files
        if ($io) {
            $io->write('  - Creating actual build');
        }

        $buildGenerator = new BuildGenerator($resourceFolder, $buildFolder);
        $buildGenerator
            ->setLogger($logger)
            ->setCollectionCreator($collectionCreator)
            ->setWriterCollection($writerCollection)
            ->run($buildNumber)
        ;

        $jsonFile           = $buildFolder . '/browscap.preprocessed.json';
        $buildJsonGenerator = new BrowscapJsonGenerator($resourceFolder, $buildFolder);
        $buildJsonGenerator
            ->setLogger($logger)
            ->run($buildNumber, $jsonFile)
        ;

        // Generate the metadata for the site
        if ($io) {
            $io->write('  - Generating metadata');
        }

        // Update the symlink
        if ($io) {
            $io->write('  - Updating symlink to point to ' . $buildNumber);
        }

        self::createTestfiles($buildFolder);
    }

    /**
     * creates the testfiles for browscap.js
     *
     * @param string $buildFolder
     */
    private static function createTestfiles($buildFolder)
    {
        if (!file_exists($buildFolder . 'test/')) {
            mkdir($buildFolder . 'test/', 0775, true);
        }

        $sourceDirectory = 'vendor/browscap/browscap/tests/fixtures/issues/';

        $iterator = new \RecursiveDirectoryIterator($sourceDirectory);

        foreach (new \RecursiveIteratorIterator($iterator) as $file) {
            /** @var $file \SplFileInfo */
            if (!$file->isFile() || $file->getExtension() != 'php') {
                continue;
            }

            self::createTestFile($file, $buildFolder);
        }
    }

    /**
     * @param \SplFileInfo $file
     * @param string       $buildFolder
     */
    private static function createTestFile(\SplFileInfo $file, $buildFolder)
    {
        $filename    = str_replace('.php', '.js', $file->getFilename());
        $testnumber  = str_replace('issue-', '', $file->getBasename($file->getExtension()));
        $filecontent = 'var assert = require(\'assert\'),
    browscap = require(\'../browscap.js\'),
    browser;

suite(\'checking for issue ' . $testnumber . '\', function () {
';

        $tests = require_once $file->getPathname();

        $propertyHolder = new PropertyHolder();

        foreach ($tests as $key => $test) {
            if (isset($data[$key])) {
                throw new \RuntimeException('Test data is duplicated for key "' . $key . '"');
            }

            if (isset($checks[$test[0]])) {
                throw new \RuntimeException(
                    'UA "' . $test[0] . '" added more than once, now for key "' . $key . '", before for key "'
                    . $checks[$test[0]] . '"'
                );
            }

            $filecontent .= '  test(\'' . $key . '\', function () {' . "\n";

            $rule = $test[0];
            $rule = str_replace(array('\\', '"'), array('\\\\', '\"'), $rule);

            $filecontent .= '    browser = browscap.getBrowser("' . $rule . '");' . "\n\n";

            foreach ($test[1] as $property => $value) {
                if (!$propertyHolder->isOutputProperty($property)) {
                    continue;
                }

                $valueOutput = '\'' . $value . '\'';

                switch ($propertyHolder->getPropertyType($property)) {
                    case PropertyHolder::TYPE_BOOLEAN:
                        if (true === $value || $value === 'true') {
                            $valueOutput = 'true';
                        } else {
                            $valueOutput = 'false';
                        }
                        break;
                    case PropertyHolder::TYPE_IN_ARRAY:
                        try {
                            $valueOutput = '\'' . $propertyHolder->checkValueInArray($property, $value) . '\'';
                        } catch (\InvalidArgumentException $e) {
                            $valueOutput = '""';
                        }
                        break;
                    default:
                        // nothing t do here
                        break;
                }

                $filecontent .= '    assert.strictEqual(browser[\'' . $property . '\'], ' . $valueOutput . ');' . "\n";
            }

            $filecontent .= '  });' . "\n";
        }

        $filecontent .= '});' . "\n";

        file_put_contents($buildFolder . 'test/' . $filename, $filecontent);
    }
}
