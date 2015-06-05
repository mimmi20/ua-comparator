<?php
namespace UaComparator\Source;

use BrowscapPHP\Helper\IniLoader;
use Monolog\Logger;

/**
 * Class DirectorySource
 *
 * @package UaComparator\Source
 * @author  Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 */
class DirectorySource
{
    /**
     * @param string          $uaSourceDirectory
     * @param \Monolog\Logger $logger
     *
     * @return \Generator
     * @throws \BrowscapPHP\Helper\Exception
     */
    public function getUserAgents($uaSourceDirectory, Logger $logger)
    {
        $iterator = new \RecursiveDirectoryIterator($uaSourceDirectory);
        $loader   = new IniLoader();

        foreach (new \RecursiveIteratorIterator($iterator) as $file) {
            /** @var $file \SplFileInfo */
            if (!$file->isFile()) {
                continue;
            }

            $path = $file->getPathname();

            $loader->setLocalFile($path);
            $internalLoader = $loader->getLoader();

            if ($internalLoader->isSupportingLoadingLines()) {
                if (!$internalLoader->init($path)) {
                    $logger->info('Skipping empty file "'.$file->getPathname().'"');
                    continue;
                }

                while ($internalLoader->isValid()) {
                    yield $internalLoader->getLine();
                }

                $internalLoader->close();
            } else {
                $lines = file($path);

                if (empty($lines)) {
                    $logger->info('Skipping empty file "'.$file->getPathname().'"');
                    continue;
                }

                foreach ($lines as $line) {
                    yield $line;
                }
            }
        }
    }
}
