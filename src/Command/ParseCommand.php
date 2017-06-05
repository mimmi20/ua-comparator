<?php
/**
 * This file is part of the ua-comparator package.
 *
 * Copyright (c) 2015-2017, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);
namespace UaComparator\Command;

use BrowscapHelper\Source\BrowscapSource;
use BrowscapHelper\Source\CollectionSource;
use BrowscapHelper\Source\DetectorSource;
use BrowscapHelper\Source\DirectorySource;
use BrowscapHelper\Source\PiwikSource;
use BrowscapHelper\Source\UapCoreSource;
use BrowscapHelper\Source\WhichBrowserSource;
use BrowscapHelper\Source\WootheeSource;
use Monolog\Handler\PsrHandler;
use Monolog\Logger;
use Noodlehaus\Config;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use UaComparator\Module\ModuleCollection;
use UaDataMapper\InputMapper;

/**
 * Class CompareCommand
 *
 * @category   UaComparator
 *
 * @author     Thomas Müller <mimmi20@live.de>
 */
class ParseCommand extends Command
{
    const SOURCE_SQL  = 'sql';
    const SOURCE_DIR  = 'dir';
    const SOURCE_TEST = 'tests';

    /**
     * @var array
     */
    private $defaultModules = [];

    /**
     * @var \Monolog\Logger
     */
    private $logger = null;

    /**
     * @var \Psr\Cache\CacheItemPoolInterface
     */
    private $cache = null;

    /**
     * @var \Noodlehaus\Config;
     */
    private $config = null;

    /**
     * @param \Monolog\Logger                   $logger
     * @param \Psr\Cache\CacheItemPoolInterface $cache
     * @param \Noodlehaus\Config                $config
     */
    public function __construct(Logger $logger, CacheItemPoolInterface $cache, Config $config)
    {
        $this->logger = $logger;
        $this->cache  = $cache;
        $this->config = $config;

        parent::__construct();
    }

    /**
     * Configures the current command.
     */
    protected function configure()
    {
        foreach ($this->config['modules'] as $key => $moduleConfig) {
            if (!$moduleConfig['enabled'] || !$moduleConfig['name'] || !$moduleConfig['class']) {
                continue;
            }

            $this->defaultModules[] = $key;
        }

        $this->defaultModules = array_unique($this->defaultModules);

        $this
            ->setName('parse')
            ->setDescription('parses uaseragents with different useragent parsers')
            ->addOption(
                'modules',
                '-m',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'The Modules to compare',
                $this->defaultModules
            )
            ->addOption(
                'limit',
                '-l',
                InputOption::VALUE_OPTIONAL,
                'the amount of useragents to compare'
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
     * @param \Symfony\Component\Console\Input\InputInterface   $input  An InputInterface instance
     * @param \Symfony\Component\Console\Output\OutputInterface $output An OutputInterface instance
     *
     * @throws \LogicException When this abstract method is not implemented
     *
     * @return null|int null or 0 if everything went fine, or an error code
     *
     * @see    setCode()
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {
        $output->writeln('preparing App ...');

        $consoleLogger = new ConsoleLogger($output);
        $this->logger->pushHandler(new PsrHandler($consoleLogger));

        $output->writeln('preparing modules ...');

        $modules    = $input->getOption('modules');
        $collection = new ModuleCollection();

        /*******************************************************************************
         * BrowserDetector
         */

        $inputMapper = new InputMapper();

        foreach ($modules as $module) {
            foreach ($this->config['modules'] as $key => $moduleConfig) {
                if ($key !== $module) {
                    continue;
                }

                if (!$moduleConfig['enabled'] || !$moduleConfig['name'] || !$moduleConfig['class']) {
                    continue;
                }

                $output->writeln('    preparing module ' . $moduleConfig['name'] . ' ...');

                if (!isset($moduleConfig['requires-cache'])) {
                    $moduleCache = new ArrayAdapter();
                } elseif ($moduleConfig['requires-cache'] && isset($moduleConfig['cache-dir'])) {
                    $moduleCache = new FilesystemAdapter('', 0, $moduleConfig['cache-dir']);
                } else {
                    $moduleCache = new ArrayAdapter();
                }

                $moduleClassName = '\\UaComparator\\Module\\' . $moduleConfig['class'];

                /** @var \UaComparator\Module\ModuleInterface $detectorModule */
                $detectorModule = new $moduleClassName($this->logger, $moduleCache);
                $detectorModule->setName($moduleConfig['name']);
                $detectorModule->setConfig($moduleConfig['request']);

                $checkName = '\\UaComparator\\Module\\Check\\' . $moduleConfig['check'];
                $detectorModule->setCheck(new $checkName());

                $mapperName = '\\UaComparator\\Module\\Mapper\\' . $moduleConfig['mapper'];
                /** @var \UaComparator\Module\Mapper\MapperInterface $mapper */
                $mapper = new $mapperName($inputMapper, $moduleCache);
                $detectorModule->setMapper($mapper);

                $collection->addModule($detectorModule);
            }
        }

        /*******************************************************************************
         * init Modules
         */

        $output->writeln('initializing modules ...');

        foreach ($collection->getModules() as $module) {
            $output->writeln('    initializing module ' . $module->getName() . ' ...');

            $module->init();
        }

        /*******************************************************************************
         * initialize Source
         */

        $output->writeln('initializing sources ...');

        $source = new CollectionSource(
            [
                new BrowscapSource($this->logger, $this->cache),
                new PiwikSource($this->logger, $this->cache),
                new UapCoreSource($this->logger),
                new WhichBrowserSource($this->logger, $this->cache),
                new WootheeSource($this->logger, $this->cache),
                new DetectorSource($this->logger, $this->cache),
                new DirectorySource($this->logger, 'data/useragents'),
            ]
        );

        /*******************************************************************************
         * Loop
         */

        $output->writeln('start Loop ...');

        $limit         = (int) $input->getOption('limit');
        $counter       = 1;
        $existingTests = [];

        foreach ($source->getUserAgents($limit) as $agent) {
            $agent = trim($agent);

            if (isset($existingTests[$agent])) {
                continue;
            }

            if (0 < $limit) {
                $output->writeln('        parsing ua #' . sprintf('%1$08d', $counter) . ': ' . $agent . ' ...');
            }

            $bench = [
                'agent' => $agent,
            ];

            /***************************************************************************
             * handle modules
             */
            $cacheId = hash('sha512', bin2hex($agent));

            if (!file_exists('data/results/' . $cacheId)) {
                mkdir('data/results/' . $cacheId, 0775, true);
            }

            foreach ($collection as $module) {
                /* @var \UaComparator\Module\ModuleInterface $module */
                $module
                    ->startTimer()
                    ->detect($agent)
                    ->endTimer();

                $detectionResult = $module->getDetectionResult();
                $actualTime      = $module->getTime();
                $actualMemory    = $module->getMaxMemory();

                // per useragent benchmark
                $bench[$module->getName()] = [
                    'time'   => $actualTime,
                    'memory' => $actualMemory,
                ];

                file_put_contents(
                    'data/results/' . $cacheId . '/' . $module->getName() . '.json',
                    json_encode(
                        [
                            'ua'     => $agent,
                            'result' => (null === $detectionResult ? null : $detectionResult->toArray()),
                            'time'   => $actualTime,
                            'memory' => $actualMemory,
                        ],
                        JSON_PRETTY_PRINT | JSON_FORCE_OBJECT
                    )
                );
            }

            file_put_contents(
                'data/results/' . $cacheId . '/bench.json',
                json_encode($bench, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT)
            );

            ++$counter;

            $existingTests[$agent] = 1;
        }
    }
}
