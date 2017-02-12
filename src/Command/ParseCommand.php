<?php
/**
 * Copyright (c) 2015, Thomas Mueller <mimmi20@live.de>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @category  UaComparator
 *
 * @author    Thomas Mueller <mimmi20@live.de>
 * @copyright 2015 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 *
 * @link      https://github.com/mimmi20/ua-comparator
 */

namespace UaComparator\Command;

use BrowscapHelper\Source\BrowscapSource;
use BrowscapHelper\Source\CollectionSource;
use BrowscapHelper\Source\DetectorSource;
use BrowscapHelper\Source\DirectorySource;
use BrowscapHelper\Source\PiwikSource;
use BrowscapHelper\Source\UapCoreSource;
use BrowscapHelper\Source\WhichBrowserSource;
use BrowscapHelper\Source\WootheeSource;
use Cache\Adapter\Filesystem\FilesystemCachePool;
use Cache\Adapter\PHPArray\ArrayCachePool;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Monolog\ErrorHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\MemoryPeakUsageProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Noodlehaus\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
    private $defaultModules = [];

    const SOURCE_SQL  = 'sql';
    const SOURCE_DIR  = 'dir';
    const SOURCE_TEST = 'tests';

    /**
     * Configures the current command.
     */
    protected function configure()
    {
        $config = new Config(['data/configs/config.json']);

        foreach ($config['modules'] as $key => $moduleConfig) {
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
        $output->writeln('preparing logger ...');

        $logger = new Logger('ua-comparator');
        $stream = new StreamHandler('log/error.log', Logger::WARNING);
        $stream->setFormatter(new LineFormatter('[%datetime%] %channel%.%level_name%: %message% %extra%' . "\n"));

        /** @var callable $memoryProcessor */
        $memoryProcessor = new MemoryUsageProcessor(true);
        $logger->pushProcessor($memoryProcessor);

        /** @var callable $peakMemoryProcessor */
        $peakMemoryProcessor = new MemoryPeakUsageProcessor(true);
        $logger->pushProcessor($peakMemoryProcessor);

        $logger->pushHandler($stream);
        ErrorHandler::register($logger);

        $output->writeln('preparing cache ...');

        $adapter      = new Local('data/cache/general/');
        $generalCache = new FilesystemCachePool(new Filesystem($adapter));

        $output->writeln('preparing App ...');

        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', 0);
        ini_set('max_input_time', 0);
        ini_set('display_errors', 1);
        ini_set('error_log', './error.log');
        error_reporting(E_ALL | E_DEPRECATED);

        date_default_timezone_set('Europe/Berlin');
        setlocale(LC_CTYPE, 'de_DE@euro', 'de_DE', 'de', 'ge');

        $output->writeln('preparing modules ...');

        $modules    = $input->getOption('modules');
        $collection = new ModuleCollection();

        /*******************************************************************************
         * BrowserDetector
         */

        $config = new Config(['data/configs/config.json']);

        $inputMapper = new InputMapper();

        foreach ($modules as $module) {
            foreach ($config['modules'] as $key => $moduleConfig) {
                if ($key !== $module) {
                    continue;
                }

                if (!$moduleConfig['enabled'] || !$moduleConfig['name'] || !$moduleConfig['class']) {
                    continue;
                }

                $output->writeln('    preparing module ' . $moduleConfig['name'] . ' ...');

                if (!isset($moduleConfig['requires-cache'])) {
                    $moduleCache = new ArrayCachePool();
                } elseif ($moduleConfig['requires-cache'] && isset($moduleConfig['cache-dir'])) {
                    $adapter     = new Local($moduleConfig['cache-dir']);
                    $moduleCache = new FilesystemCachePool(new Filesystem($adapter));
                } else {
                    $moduleCache = new ArrayCachePool();
                }

                $moduleClassName = '\\UaComparator\\Module\\' . $moduleConfig['class'];

                /** @var \UaComparator\Module\ModuleInterface $detectorModule */
                $detectorModule = new $moduleClassName($logger, $moduleCache);
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

        $limit         = (int) $input->getOption('limit');
        $i             = 1;
        $existingTests = [];

        $source  = new CollectionSource(
            [
                new BrowscapSource($logger, $output, $generalCache),
                new PiwikSource($logger, $output),
                new UapCoreSource($logger, $output),
                new WhichBrowserSource($logger, $output),
                new WootheeSource($logger, $output),
                new DetectorSource($logger, $output, $generalCache),
                new DirectorySource($logger, $output, 'data/useragents'),
            ]
        );

        /*******************************************************************************
         * Loop
         */

        $output->writeln('start Loop ...');

        foreach ($source->getUserAgents($limit) as $agent) {
            $agent = trim($agent);

            if (isset($existingTests[$agent])) {
                continue;
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
                            'result' => $detectionResult,
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

            ++$i;

            $existingTests[$agent] = 1;
        }
    }
}
