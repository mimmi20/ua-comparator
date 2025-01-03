<?php

/**
 * This file is part of the mimmi20/ua-comparator package.
 *
 * Copyright (c) 2015-2025, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace UaComparator\Command;

use BrowscapHelper\Source\BrowscapSource;
use BrowscapHelper\Source\BrowserDetectorSource;
use BrowscapHelper\Source\CollectionSource;
use BrowscapHelper\Source\DirectorySource;
use BrowscapHelper\Source\MatomoSource;
use BrowscapHelper\Source\UapCoreSource;
use BrowscapHelper\Source\WhichBrowserSource;
use BrowscapHelper\Source\WootheeSource;
use League\Flysystem\Filesystem;
use LogicException;
use MatthiasMullie\Scrapbook\Adapters\Flysystem;
use MatthiasMullie\Scrapbook\Adapters\MemoryStore;
use MatthiasMullie\Scrapbook\Psr16\SimpleCache;
use Monolog\Handler\PsrHandler;
use Monolog\Logger;
use Noodlehaus\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use UaComparator\Module\Mapper\MapperInterface;
use UaComparator\Module\ModuleCollection;
use UaComparator\Module\ModuleInterface;
use UaDataMapper\InputMapper;

use function assert;
use function bin2hex;
use function file_exists;
use function file_put_contents;
use function hash;
use function is_array;
use function json_encode;
use function mkdir;
use function sprintf;
use function trim;

use const JSON_FORCE_OBJECT;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

final class ParseCommand extends Command
{
    public const string SOURCE_SQL = 'sql';

    public const string SOURCE_DIR = 'dir';

    public const string SOURCE_TEST = 'tests';

    /** @throws void */
    public function __construct(private readonly Logger $logger, private readonly Config $config)
    {
        parent::__construct();
    }

    /**
     * Configures the current command.
     *
     * @throws void
     */
    protected function configure(): void
    {
        $this
            ->setName('parse')
            ->setDescription('parses uaseragents with different useragent parsers')
            ->addOption(
                'limit',
                '-l',
                InputOption::VALUE_OPTIONAL,
                'the amount of useragents to compare',
            );
    }

    /**
     * Executes the current command.
     *
     * This method is not abstract because you can use this class
     * as a concrete class. In this case, instead of defining the
     * execute() method, you set the code to execute by passing
     * a Closure to the setCode() method.
     *
     * @see    setCode()
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     *
     * @return int|null null or 0 if everything went fine, or an error code
     *
     * @throws LogicException When this abstract method is not implemented
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('preparing App ...');

        $consoleLogger = new ConsoleLogger($output);
        $this->logger->pushHandler(new PsrHandler($consoleLogger));

        $output->writeln('preparing modules ...');

        $modules    = $input->getOption('modules');
        $collection = new ModuleCollection();

        /*
         * BrowserDetector
         */

        $inputMapper = new InputMapper();

        foreach ($modules as $module) {
            foreach ($this->config['modules'] as $key => $moduleConfig) {
                if ($key !== $module) {
                    continue;
                }

                assert(is_array($moduleConfig));

                if (!$moduleConfig['enabled'] || !$moduleConfig['name'] || !$moduleConfig['class']) {
                    continue;
                }

                $output->writeln('    preparing module ' . $moduleConfig['name'] . ' ...');

                if (!isset($moduleConfig['requires-cache'])) {
                    $moduleCache = new SimpleCache(
                        new MemoryStore(),
                    );
                } elseif ($moduleConfig['requires-cache'] && isset($moduleConfig['cache-dir'])) {
                    $moduleCache = new SimpleCache(
                        new Flysystem(
                            new Filesystem($moduleConfig['cache-dir']),
                        ),
                    );
                } else {
                    $moduleCache = new SimpleCache(
                        new MemoryStore(),
                    );
                }

                $moduleClassName = '\UaComparator\Module\\' . $moduleConfig['class'];

                $detectorModule = new $moduleClassName($this->logger, $moduleCache);
                assert($detectorModule instanceof ModuleInterface);
                $detectorModule->setName($moduleConfig['name']);
                $detectorModule->setConfig($moduleConfig['request']);

                $checkName = '\UaComparator\Module\Check\\' . $moduleConfig['check'];
                $detectorModule->setCheck(new $checkName());

                $mapperName = '\UaComparator\Module\Mapper\\' . $moduleConfig['mapper'];
                $mapper     = new $mapperName($inputMapper, $moduleCache);
                assert($mapper instanceof MapperInterface);
                $detectorModule->setMapper($mapper);

                $collection->addModule($detectorModule);
            }
        }

        /*
         * init Modules
         */

        $output->writeln('initializing modules ...');

        foreach ($collection->getModules() as $module) {
            $output->writeln('    initializing module ' . $module->getName() . ' ...');

            $module->init();
        }

        /*
         * initialize Source
         */

        $output->writeln('initializing sources ...');

        $source = new CollectionSource(
            new BrowscapSource(),
            new MatomoSource(),
            new UapCoreSource(),
            new WhichBrowserSource(),
            new WootheeSource(),
            new BrowserDetectorSource(),
            new DirectorySource('data/useragents'),
        );

        /*
         * Loop
         */

        $output->writeln('start Loop ...');

        $limit         = (int) $input->getOption('limit');
        $counter       = 1;
        $existingTests = [];

        foreach ($source->getUserAgents('') as $agent) {
            $agent = trim((string) $agent);

            if (isset($existingTests[$agent])) {
                continue;
            }

            if (0 < $limit) {
                $output->writeln(
                    '        parsing ua #' . sprintf('%1$08d', $counter) . ': ' . $agent . ' ...',
                );
            }

            $bench = ['agent' => $agent];

            /*
             * handle modules
             */
            $cacheId = hash('sha512', bin2hex($agent));

            if (!file_exists('data/results/' . $cacheId)) {
                mkdir('data/results/' . $cacheId, 0775, true);
            }

            foreach ($collection as $module) {
                /** @var ModuleInterface $module */
                $module
                    ->startTimer()
                    ->detect($agent)
                    ->endTimer();

                $detectionResult = $module->getDetectionResult();
                $actualTime      = $module->getTime();
                $actualMemory    = $module->getMaxMemory();

                // per useragent benchmark
                $bench[$module->getName()] = [
                    'memory' => $actualMemory,
                    'time' => $actualTime,
                ];

                file_put_contents(
                    'data/results/' . $cacheId . '/' . $module->getName() . '.json',
                    json_encode(
                        [
                            'memory' => $actualMemory,
                            'result' => $detectionResult?->toArray(),
                            'time' => $actualTime,
                            'ua' => $agent,
                        ],
                        JSON_PRETTY_PRINT | JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR,
                    ),
                );
            }

            file_put_contents(
                'data/results/' . $cacheId . '/bench.json',
                json_encode($bench, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR),
            );

            ++$counter;

            $existingTests[$agent] = 1;
        }

        return self::SUCCESS;
    }
}
