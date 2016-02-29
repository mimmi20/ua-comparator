<?php
/**
 * Copyright (c) 2015, Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
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
 * @author    Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 * @copyright 2015 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 *
 * @link      https://github.com/mimmi20/ua-comparator
 */

namespace UaComparator\Command;

use Browscap\Generator\BuildGenerator;
use Browscap\Helper\CollectionCreator;
use Browscap\Writer\Factory\PhpWriterFactory;
use Monolog\ErrorHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\MemoryPeakUsageProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Noodlehaus\Config;
use PDO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use UaComparator\Helper\Check;
use UaComparator\Helper\MessageFormatter;
use UaComparator\Helper\TimeFormatter;
use UaComparator\Module\Browscap2;
use UaComparator\Module\Browscap3;
use UaComparator\Module\BrowserDetectorModule;
use UaComparator\Module\CrossJoin;
use UaComparator\Module\DeviceAtlasCom;
use UaComparator\Module\DonatjUAParser;
use UaComparator\Module\Http;
use UaComparator\Module\ModuleCollection;
use UaComparator\Module\NeutrinoApiCom;
use UaComparator\Module\PiwikDetector;
use UaComparator\Module\SinergiBrowserDetector;
use UaComparator\Module\UaParser;
use UaComparator\Module\UdgerCom;
use UaComparator\Module\UserAgentApiCom;
use UaComparator\Module\UserAgentStringCom;
use UaComparator\Module\WhatIsMyBrowserCom;
use UaComparator\Module\WhichBrowser;
use UaComparator\Module\Woothee;
use UaComparator\Module\Wurfl;
use UaComparator\Module\WurflOld;
use UaComparator\Source\DirectorySource;
use UaComparator\Source\PdoSource;
use WurflCache\Adapter\File;
use WurflCache\Adapter\Memory;
use UaDataMapper\InputMapper;

define('START_TIME', microtime(true));

/**
 * Class CompareCommand
 *
 * @category   UaComparator
 *
 * @author     Thomas Müller <t_mueller_stolzenhain@yahoo.de>
 */
class CompareCommand extends Command
{
    const COL_LENGTH       = 50;
    const FIRST_COL_LENGTH = 20;

    private $defaultModules = [];

    /**
     * Configures the current command.
     */
    protected function configure()
    {
        $config = new Config(['data/configs/config.dist.json', '?data/configs/config.json']);

        foreach ($config['modules'] as $key => $moduleConfig) {
            if (!$moduleConfig['enabled'] || !$moduleConfig['name'] || !$moduleConfig['class']) {
                continue;
            }

            $this->defaultModules[] = $moduleConfig['class'];
        }

        $allChecks = [
            Check::MINIMUM,
            Check::MEDIUM,
        ];

        $this
            ->setName('compare')
            ->setDescription('compares different useragent parsers')
            ->addOption(
                'modules',
                '-m',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'The Modules to compare',
                $this->defaultModules
            )
            ->addOption(
                'check-level',
                '-c',
                InputOption::VALUE_REQUIRED,
                'the level for the checks to do. Available Options:' . implode(',', $allChecks),
                Check::MINIMUM
            )
            ->addOption(
                'limit',
                '-l',
                InputOption::VALUE_OPTIONAL,
                'the amount of useragents to compare'
            )
            //            ->addArgument('version', InputArgument::REQUIRED, 'Version number to apply')
            //            ->addOption('resources', null, InputOption::VALUE_REQUIRED, 'Where the resource files are located', $defaultResourceFolder)
            //            ->addOption('debug', null, InputOption::VALUE_NONE, 'Should the debug mode entered?')
        ;
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
        $logger = new Logger('ua-comparator');

        $stream = new StreamHandler('php://output', Logger::ERROR);
        $stream->setFormatter(new LineFormatter('[%datetime%] %channel%.%level_name%: %message% %extra%' . "\n"));

        /** @var callable $memoryProcessor */
        $memoryProcessor = new MemoryUsageProcessor(true);
        $logger->pushProcessor($memoryProcessor);

        /** @var callable $peakMemoryProcessor */
        $peakMemoryProcessor = new MemoryPeakUsageProcessor(true);
        $logger->pushProcessor($peakMemoryProcessor);

        $logger->pushHandler($stream);
        $logger->pushHandler(new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Logger::ERROR));

        ErrorHandler::register($logger);

        $output->write('initializing App ...', false);

        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', 0);
        ini_set('max_input_time', 0);
        ini_set('display_errors', 1);
        ini_set('error_log', './error.log');
        error_reporting(E_ALL | E_DEPRECATED);

        date_default_timezone_set('Europe/Berlin');
        setlocale(LC_CTYPE, 'de_DE@euro', 'de_DE', 'de', 'ge');

        $output->writeln(
            ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' - ' . number_format(
                memory_get_usage(true),
                0,
                ',',
                '.'
            ) . ' Bytes'
        );

        $modules    = $input->getOption('modules');
        $collection = new ModuleCollection();

        /*******************************************************************************
         * BrowserDetector
         */

        $config = new Config(['data/configs/config.dist.json', '?data/configs/config.json']);

        $inputMapper = new InputMapper();

        foreach ($modules as $module) {
            foreach ($config['modules'] as $key => $moduleConfig) {
                if (!$moduleConfig['enabled'] || !$moduleConfig['name'] || !$moduleConfig['class']) {
                    continue;
                }

                if ($moduleConfig['class'] === $module) {
                    $output->write('initializing ' . $moduleConfig['name'] . ' ...', false);

                    if (!isset($moduleConfig['requires-cache'])) {
                        $cache = new Memory();
                    } elseif ($moduleConfig['requires-cache'] && isset($moduleConfig['cache-dir'])) {
                        $cache = new File([File::DIR => $moduleConfig['cache-dir']]);
                    } else {
                        $cache = new Memory();
                    }

                    $moduleClassName = '\\UaComparator\\Module\\' . $moduleConfig['class'];

                    /** @var \UaComparator\Module\ModuleInterface $detectorModule */
                    $detectorModule = new $moduleClassName($logger, $cache);
                    $detectorModule->setName($moduleConfig['name']);
                    $detectorModule->setConfig($moduleConfig['request']);

                    $checkName = '\\UaComparator\\Module\\Check\\' . $moduleConfig['check'];
                    $detectorModule->setCheck(new $checkName());

                    $mapperName = '\\UaComparator\\Module\\Mapper\\' . $moduleConfig['mapper'];
                    /** @var \UaComparator\Module\Mapper\MapperInterface $mapper */
                    $mapper     = new $mapperName();
                    $mapper->setMapper($inputMapper);
                    $detectorModule->setMapper($mapper);

                    $collection->addModule($detectorModule);

                    $output->writeln(
                        ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' - ' . number_format(
                            memory_get_usage(true),
                            0,
                            ',',
                            '.'
                        ) . ' Bytes'
                    );

                    break;
                }
            }
        }

        /*******************************************************************************
         * BrowserDetector
         *

        $output->write('initializing BrowserDetectorModule ...', false);

        $detectorModule = new BrowserDetectorModule($logger, new File([File::DIR => 'data/cache/browser/']));
        $detectorModule->setId(0)->setName('BrowserDetector');

        $collection->addModule($detectorModule);

        $output->writeln(
            ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' - ' . number_format(
                memory_get_usage(true),
                0,
                ',',
                '.'
            ) . ' Bytes'
        );

        /*******************************************************************************
         * checking full_php_browscap.ini
         *

        $iniFile = null;

        if (in_array('Browscap3', $modules) || in_array('Browscap2', $modules) || in_array('CrossJoin', $modules)) {
            $output->write('checking full_php_browscap.ini ...', false);

            $buildNumber = (int) file_get_contents('vendor/browscap/browscap/BUILD_NUMBER');
            $iniFile     = 'data/browscap-ua-test-' . $buildNumber . '/full_php_browscap.ini';

            if (!file_exists($iniFile)) {
                $resourceFolder = 'vendor/browscap/browscap/resources/';
                $buildFolder    = 'data/browscap-ua-test-' . $buildNumber . '/';

                if (!file_exists($buildFolder)) {
                    mkdir($buildFolder);
                }

                $buildGenerator = new BuildGenerator(
                    $resourceFolder,
                    $buildFolder
                );

                $writerCollectionFactory = new PhpWriterFactory();
                $writerCollection        = $writerCollectionFactory->createCollection($logger, $buildFolder);

                $buildGenerator
                    ->setLogger($logger)
                    ->setCollectionCreator(new CollectionCreator())
                    ->setWriterCollection($writerCollection);

                $buildGenerator->run('test', false);
            }

            $output->writeln(
                ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' - ' . number_format(
                    memory_get_usage(true),
                    0,
                    ',',
                    '.'
                ) . ' Bytes'
            );
        }

        /*******************************************************************************
         * Browscap-PHP 3.x
         *

        if (in_array('Browscap3', $modules) && null !== $iniFile) {
            $output->write('initializing Browscap-PHP (3.x) ...', false);

            $browscap3Module = new Browscap3($logger, new File([File::DIR => 'data/cache/browscap3/']), $iniFile);
            $browscap3Module->setId(9)->setName('Browscap-PHP (3.x)');

            $collection->addModule($browscap3Module);

            $output->writeln(
                ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' - ' . number_format(
                    memory_get_usage(true),
                    0,
                    ',',
                    '.'
                ) . ' Bytes'
            );
        }

        /*******************************************************************************
         * Browscap-PHP 2.x
         *

        if (in_array('Browscap2', $modules) && null !== $iniFile) {
            $output->write('initializing Browscap-PHP (2.x) ...', false);

            $browscap2Module = new Browscap2($logger, new Memory(), 'data/cache/browscap2/', $iniFile);
            $browscap2Module->setId(13)->setName('Browscap-PHP (2.x)');

            $collection->addModule($browscap2Module);

            $output->writeln(
                ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' - ' . number_format(
                    memory_get_usage(true),
                    0,
                    ',',
                    '.'
                ) . ' Bytes'
            );
        }

        /*******************************************************************************
         * Crossjoin\Browscap
         *

        if (in_array('CrossJoin', $modules) && null !== $iniFile) {
            $output->write('initializing Crossjoin\Browscap ...', false);

            $crossjoinModule = new CrossJoin($logger, new File([File::DIR => 'data/cache/crossjoin/']), $iniFile);
            $crossjoinModule->setId(10)->setName('Crossjoin\Browscap');

            $collection->addModule($crossjoinModule);

            $output->writeln(
                ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' - ' . number_format(
                    memory_get_usage(true),
                    0,
                    ',',
                    '.'
                ) . ' Bytes'
            );
        }

        /*******************************************************************************
         * UAParser
         *

        if (in_array('UaParser', $modules)) {
            $output->write('initializing UAParser ...', false);

            $uaparserModule = new UaParser($logger, new File([File::DIR => 'data/cache/uaparser/']));
            $uaparserModule->setId(5)->setName('UAParser');

            $collection->addModule($uaparserModule);

            $output->writeln(
                ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' - ' . number_format(
                    memory_get_usage(true),
                    0,
                    ',',
                    '.'
                ) . ' Bytes'
            );
        }

        /*******************************************************************************
         * UASParser
         *
         * if (in_array('UASParser', $modules)) {
         * $output->write('initializing UASParser ...', false);
         *
         * $uasparserModule = new UasParser($logger, new File(array(File::DIR => 'data/cache/uasparser/')));
         * $uasparserModule
         * ->setId(6)
         * ->setName('UASParser')
         * ;
         *
         * $collection->addModule($uasparserModule);
         *
         * $output->writeln(' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' - '
         * . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes');
         * }
         */

        /*******************************************************************************
         * WURFL - PHP 5.3 port
         *

        if (in_array('Wurfl', $modules)) {
            $output->write('initializing Wurfl API (PHP-API 5.3 port) ...', false);

            ini_set('max_input_time', '6000');
            $wurflModule = new Wurfl(
                $logger,
                new File([File::DIR => 'data/cache/wurfl/']),
                'data/wurfl-config.xml'
            );
            $wurflModule->setId(11)->setName('WURFL API (PHP-API 5.3)');

            $collection->addModule($wurflModule);

            $output->writeln(
                ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' - ' . number_format(
                    memory_get_usage(true),
                    0,
                    ',',
                    '.'
                ) . ' Bytes'
            );
        }

        /*******************************************************************************
         * WURFL - PHP 5.2 original
         *

        if (in_array('Wurfl52', $modules)) {
            $output->write('initializing Wurfl API (PHP-API 5.2 original) ...', false);

            $oldWurflModule = new WurflOld(
                $logger,
                new File([File::DIR => 'data/cache/wurfl_old/']),
                'data/wurfl-config.xml'
            );
            $oldWurflModule->setId(7)->setName('WURFL API (PHP-API 5.2 original)');

            $collection->addModule($oldWurflModule);

            $output->writeln(
                ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' - ' . number_format(
                    memory_get_usage(true),
                    0,
                    ',',
                    '.'
                ) . ' Bytes'
            );
        }

        /*******************************************************************************
         * init Modules
         */

        $output->write('initializing all Modules ...', false);

        $collection->init();

        $output->writeln(
            ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' - ' . number_format(
                memory_get_usage(true),
                0,
                ',',
                '.'
            ) . ' Bytes'
        );

        /*******************************************************************************
         * Loop
         */

        $i       = 1;
        $aLength = self::COL_LENGTH + 1 + self::COL_LENGTH + 1 + (($collection->count() - 1) * (self::COL_LENGTH + 1));

        $messageFormatter = new MessageFormatter();
        $messageFormatter->setCollection($collection)->setColumnsLength(self::COL_LENGTH);

        $output->write(str_repeat('+', self::FIRST_COL_LENGTH + $aLength + $collection->count() - 1 + 2), false);
        $output->writeln('');

        /*******************************************************************************
         * initialize Source
         */
        $dsn      = 'mysql:dbname=browscap;host=localhost';
        $user     = 'root';
        $password = '';

        try {
            $adapter = new PDO(
                $dsn,
                $user,
                $password,
                [
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'',
                    1005                         => 1024 * 1024 * 50, // PDO::MYSQL_ATTR_MAX_BUFFER_SIZE
                ]
            );
            $adapter->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $source = new PdoSource($adapter);
        } catch (\Exception $e) {
            $logger->debug($e);

            $uaSourceDirectory = 'data/useragents';
            $source            = new DirectorySource($uaSourceDirectory);
        }

        $benchAll = [];

        foreach ($collection as $module) {
            /* @var \UaComparator\Module\ModuleInterface $module */
            $benchAll[$module->getName()] = [
                'time' => [
                    'min'     => ['time' => 1200.0, 'agent' => ''],
                    'max'     => ['time' => 0.0, 'agent' => ''],
                    'last'    => ['time' => 0.0, 'agent' => ''],
                    'summary' => 0.0,
                ],
                'memory' => [
                    'min'     => ['size' => 500000000, 'agent' => ''],
                    'max'     => ['size' => 0, 'agent' => ''],
                    'last'    => ['size' => 0, 'agent' => ''],
                ]
            ];
        }

        $limit = (int) $input->getOption('limit');

        /*
        $okfound   = 0;
        $nokfound  = 0;
        $sosofound = 0;

        $checklevel  = $input->getOption('check-level');
        $checkHelper = new Check();
        $checks      = $checkHelper->getChecks($checklevel);
        /**/

        /*******************************************************************************
         * Loop
         */
        foreach ($source->getUserAgents($logger, $limit) as $agent) {
            $matches = [];

            /***************************************************************************
             * handle modules
             */

            $timeStart = microtime(true);

            foreach ($collection as $module) {
                /* @var \UaComparator\Module\ModuleInterface $module */
                $module
                    ->startTimer()
                    ->detect($agent)
                    ->endTimer();

                $result = $module->getDetectionResult();

                $actualTime = $module->getTime();

                $benchAll[$module->getName()]['time']['summary'] += $actualTime;

                $benchAll[$module->getName()]['time']['last']['time']  = $actualTime;
                $benchAll[$module->getName()]['time']['last']['agent'] = $agent;

                if ($benchAll[$module->getName()]['time']['min']['time'] > $actualTime) {
                    $benchAll[$module->getName()]['time']['min']['time']  = $actualTime;
                    $benchAll[$module->getName()]['time']['min']['agent'] = $agent;
                }

                if ($benchAll[$module->getName()]['time']['max']['time'] < $actualTime) {
                    $benchAll[$module->getName()]['time']['max']['time']  = $actualTime;
                    $benchAll[$module->getName()]['time']['max']['agent'] = $agent;
                }

                $actualMemory = $module->getMaxMemory();

                $benchAll[$module->getName()]['memory']['last']['size']  = $actualMemory;
                $benchAll[$module->getName()]['memory']['last']['agent'] = $agent;

                if ($benchAll[$module->getName()]['memory']['min']['size'] > $actualMemory) {
                    $benchAll[$module->getName()]['memory']['min']['size']  = $actualMemory;
                    $benchAll[$module->getName()]['memory']['min']['agent'] = $agent;
                }

                if ($benchAll[$module->getName()]['memory']['max']['size'] < $actualMemory) {
                    $benchAll[$module->getName()]['memory']['max']['size']  = $actualMemory;
                    $benchAll[$module->getName()]['memory']['max']['agent'] = $agent;
                }

                var_dump($result);
            }

            echo '.';

            $fullTime = microtime(true) - $timeStart;

            /***************************************************************************
             * handle modules - end
             */

            /*
             * Auswertung
             */

            ++$i;
        }

        var_dump($benchAll);
    }
}
