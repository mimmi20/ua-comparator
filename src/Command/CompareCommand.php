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
 * @package   UaComparator
 * @author    Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 * @copyright 2015 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 * @link      https://github.com/mimmi20/ua-comparator
 */

namespace UaComparator\Command;

use Browscap\Generator\BuildGenerator;
use Browscap\Helper\CollectionCreator;
use Browscap\Helper\LoggerHelper;
use Browscap\Writer\Factory\PhpWriterFactory;
use Monolog\ErrorHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\MemoryPeakUsageProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use PDO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use UaComparator\Helper\Check;
use UaComparator\Helper\MessageFormatter;
use UaComparator\Helper\TimeFormatter;
use UaComparator\Module\Browscap;
use UaComparator\Module\BrowserDetectorModule;
use UaComparator\Module\CrossJoin;
use UaComparator\Module\ModuleCollection;
use UaComparator\Module\PiwikDetector;
use UaComparator\Module\UaParser;
use UaComparator\Module\Wurfl;
use UaComparator\Module\WurflOld;
use UaComparator\Source\DirectorySource;
use UaComparator\Source\PdoSource;
use WurflCache\Adapter\File;
use WurflCache\Adapter\Memory;

define('ROW_LENGTH', 397);
define('COL_LENGTH', 50);
define('FIRST_COL_LENGTH', 20);

define('START_TIME', microtime(true));

define('COLOR_END', "\x1b[0m");
define('COLOR_START_RED', "\x1b[37;41m");
define('COLOR_START_YELLOW', "\x1b[30;43m");
define('COLOR_START_GREEN', "\x1b[30;42m");

/**
 * Class CompareCommand
 *
 * @category   UaComparator
 * @package    Command
 * @author     Thomas Müller <t_mueller_stolzenhain@yahoo.de>
 */
class CompareCommand extends Command
{
    /**
     * Configures the current command.
     */
    protected function configure()
    {
        $defaultModules = array(
            'BrowserDetector',
            'Browscap',
            'CrossJoin',
            'Piwik',
            'UaParser',
            'Wurfl',
            'Wurfl52',
            /*'UASParser',*/
        );

        $allChecks = array(
            Check::MINIMUM,
            Check::MEDIUM,
        );

        $this
            ->setName('compare')
            ->setDescription('compares different useragent parsers')
            ->addOption(
                'modules',
                '-m',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'The Modules to compare',
                $defaultModules
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
     * @return null|integer null or 0 if everything went fine, or an error code
     *
     * @throws \LogicException When this abstract method is not implemented
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

        /** @var callable $memoryProcessor */
        $memoryProcessor = new MemoryUsageProcessor(true);
        $logger->pushProcessor($memoryProcessor);

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
         * BrowserDetectorModule
         */

        if (in_array('BrowserDetector', $modules)) {
            $output->write('initializing BrowserDetectorModule ...', false);

            $detectorModule = new BrowserDetectorModule($logger, new File(array(File::DIR => 'data/cache/browser/')));
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
        }

        /*******************************************************************************
         * checking full_php_browscap.ini
         */

        $iniFile = null;

        if (in_array('Browscap', $modules) || in_array('CrossJoin', $modules)) {
            $output->write('checking full_php_browscap.ini ...', false);

            $buildNumber = (int)file_get_contents('vendor/browscap/browscap/BUILD_NUMBER');
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
                    ->setWriterCollection($writerCollection)
                ;

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
         * Browscap-PHP
         */

        if (in_array('Browscap', $modules) && null !== $iniFile) {
            $output->write('initializing Browscap-PHP ...', false);

            $browscapModule = new Browscap($logger, new File(array(File::DIR => 'data/cache/browscap/')), $iniFile);
            $browscapModule->setId(9)->setName('Browscap-PHP');

            $collection->addModule($browscapModule);

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
         */

        if (in_array('CrossJoin', $modules) && null !== $iniFile) {
            $output->write('initializing Crossjoin\Browscap ...', false);

            $crossjoinModule = new CrossJoin($logger, new File(array(File::DIR => 'data/cache/crossjoin/')), $iniFile);
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
         */

        if (in_array('UaParser', $modules)) {
            $output->write('initializing UAParser ...', false);

            $uaparserModule = new UaParser($logger, new File(array(File::DIR => 'data/cache/uaparser/')));
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
         */

        if (in_array('Wurfl', $modules)) {
            $output->write('initializing Wurfl API (PHP-API 5.3 port) ...', false);

            ini_set('max_input_time', '6000');
            $wurflModule = new Wurfl($logger, new File(array(File::DIR => 'data/cache/wurfl/')), 'data/wurfl-config.xml');
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
         */

        if (in_array('Wurfl52', $modules)) {
            $output->write('initializing Wurfl API (PHP-API 5.2 original) ...', false);

            $oldWurflModule = new WurflOld(
                $logger,
                new File(array(File::DIR => 'data/cache/wurfl_old/')),
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
         * Piwik Parser
         */

        if (in_array('Piwik', $modules)) {
            $output->write('initializing Piwik Parser ...', false);

            $adapter     = new Memory();
            $piwikModule = new PiwikDetector($logger, $adapter);
            $piwikModule->setId(12)->setName('Piwik Parser');

            $collection->addModule($piwikModule);

            $output->writeln(
                ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' - ' . number_format(
                    memory_get_usage(true),
                    0,
                    ',',
                    '.'
                ) . ' Bytes'
            );
        }

        $output->writeln('initializing all Modules ...');

        $collection->init();

        $output->writeln(
            'ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' - ' . number_format(
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
        $count   = 0;
        $aLength = COL_LENGTH + 1 + COL_LENGTH + 1 + (($collection->count() - 1) * (COL_LENGTH + 1));

        $messageFormatter = new MessageFormatter();
        $messageFormatter->setCollection($collection)->setColumnsLength(COL_LENGTH);

        $output->write(str_repeat('+', FIRST_COL_LENGTH + $aLength + $collection->count() - 1 + 2), false);

        $okfound   = 0;
        $nokfound  = 0;
        $sosofound = 0;

        $output->writeln('');

        $checklevel  = $input->getOption('check-level');
        $checkHelper = new Check();
        $checks      = $checkHelper->getChecks($checklevel, $collection);

        /*******************************************************************************
         * Loop
         */
        $dsn      = 'mysql:dbname=browscap;host=localhost';
        $user     = 'root';
        $password = '';

        try {
            $adapter = new PDO(
                $dsn,
                $user,
                $password,
                array(
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'',
                    1005 => 1024 * 1024 * 50, // PDO::MYSQL_ATTR_MAX_BUFFER_SIZE
                )
            );
            $adapter->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $source = new PdoSource($adapter);
        } catch (\Exception $e) {
            $logger->debug($e);

            $uaSourceDirectory = 'data/useragents';
            $source            = new DirectorySource($uaSourceDirectory);
        }

        $allTimes = array();

        foreach ($collection as $module) {
            $allTimes[$module->getName()] = array(
                'min'     => array('time' => 1.0, 'agent' => ''),
                'max'     => array('time' => 0.0, 'agent' => ''),
                'summary' => 0.0,
            );
        }

        $limit = (int) $input->getOption('limit');

        foreach ($source->getUserAgents($logger, $limit) as $agent) {
            $matches = array();

            /***************************************************************************
             * handle modules
             */

            foreach ($collection as $module) {
                $module
                    ->startTimer()
                    ->detect($agent)
                    ->endTimer()
                ;

                $actualTime = $module->getTime();

                $allTimes[$module->getName()]['summary'] += $actualTime;

                if ($allTimes[$module->getName()]['min']['time'] > $actualTime) {
                    $allTimes[$module->getName()]['min']['time']  = $actualTime;
                    $allTimes[$module->getName()]['min']['agent'] = $agent;
                }

                if ($allTimes[$module->getName()]['max']['time'] < $actualTime) {
                    $allTimes[$module->getName()]['max']['time']  = $actualTime;
                    $allTimes[$module->getName()]['max']['agent'] = $agent;
                }
            }

            /***************************************************************************
             * handle modules - end
             */

            /**
             * Auswertung
             */
            $allResults = array();

            foreach ($checks as $propertyTitel => $x) {
                if (empty($x['key'])) {
                    $propertyName = $propertyTitel;
                } else {
                    $propertyName = $x['key'];
                }

                $detectionResults = $messageFormatter->formatMessage($propertyTitel, $propertyName);

                foreach ($detectionResults as $result) {
                    $matches[] = substr($result, 0, 1);
                }

                $allResults[$propertyTitel] = $detectionResults;
            }

            if (in_array('-', $matches)) {
                $content = file_get_contents('src/templates/single-line.txt');
                $content = str_replace('#ua#', $agent, $content);
                $content = str_replace('#               id#', str_pad($i, FIRST_COL_LENGTH, ' ', STR_PAD_LEFT), $content);
                foreach ($collection as $module) {
                    $content = str_replace('#' . $module->getName() . '#', number_format($module->getTime(), 10, ',', '.'), $content);
                }

                $content .= file_get_contents('src/templates/result-head.txt');
                $content = str_replace('#ua#', $agent, $content);

                foreach ($allResults as $propertyTitel => $detectionResults) {
                    $content .= file_get_contents('src/templates/result-line.txt');

                    $content = str_replace('#Title                                           #', $propertyTitel, $content);

                    foreach ($detectionResults as $key => $value) {
                        $content = str_replace($key, $value, $content);
                    }
                }

                $content .= '-';
                $nokfound++;
            } elseif (in_array(':', $matches)) {
                $content = ':';
                $sosofound++;
            } else {
                $content = '.';
                $okfound++;
            }

            if (($i % 100) == 0) {
                $content .= "\n";
            }

            if (in_array('-', $matches)) {
                $content = str_replace(
                    array(
                        '#  plus#',
                        '# minus#',
                        '#  soso#',
                        '#     percent1#',
                        '#     percent2#',
                        '#     percent3#',
                    ),
                    array(
                        str_pad($okfound, 8, ' ', STR_PAD_LEFT),
                        str_pad($nokfound, 8, ' ', STR_PAD_LEFT),
                        str_pad($sosofound, 8, ' ', STR_PAD_LEFT),
                        str_pad(
                            number_format((100 * $okfound / $i), 9, ',', '.'),
                            15,
                            ' ',
                            STR_PAD_LEFT
                        ),
                        str_pad(
                            number_format((100 * $nokfound / $i), 9, ',', '.'),
                            15,
                            ' ',
                            STR_PAD_LEFT
                        ),
                        str_pad(
                            number_format((100 * $sosofound / $i), 9, ',', '.'),
                            15,
                            ' ',
                            STR_PAD_LEFT
                        ),
                    ),
                    $content
                );
            }

            $output->write($content, false);

            $i++;
        }

        $output->writeln('');

        $content = file_get_contents('src/templates/end-line.txt');

        --$i;

        if ($i < 1) {
            $i = 1;
        }

        $content = str_replace(
            array(
                '#  plus#',
                '# minus#',
                '#  soso#',
                '#     percent1#',
                '#     percent2#',
                '#     percent3#',
            ),
            array(
                str_pad($okfound, 8, ' ', STR_PAD_LEFT),
                str_pad($nokfound, 8, ' ', STR_PAD_LEFT),
                str_pad($sosofound, 8, ' ', STR_PAD_LEFT),
                str_pad(
                    number_format((100 * $okfound / $i), 9, ',', '.'),
                    15,
                    ' ',
                    STR_PAD_LEFT
                ),
                str_pad(
                    number_format((100 * $nokfound / $i), 9, ',', '.'),
                    15,
                    ' ',
                    STR_PAD_LEFT
                ),
                str_pad(
                    number_format((100 * $sosofound / $i), 9, ',', '.'),
                    15,
                    ' ',
                    STR_PAD_LEFT
                ),
            ),
            $content
        );

        foreach ($allTimes as $moduleName => $timeData) {
            $content = str_replace(
                array(
                    '#' . $moduleName . ' - Summary#',
                    '#' . $moduleName . ' - Max#',
                    '#' . $moduleName . ' - Average#',
                    '#' . $moduleName . ' - Min#',
                ),
                array(
                    str_pad(number_format($timeData['summary'], 10, ',', '.'), 20, ' ', STR_PAD_LEFT),
                    str_pad(number_format($timeData['max']['time'], 10, ',', '.'), 20, ' ', STR_PAD_LEFT),
                    str_pad(number_format(($timeData['summary'] / $i), 10, ',', '.'), 20, ' ', STR_PAD_LEFT),
                    str_pad(number_format($timeData['min']['time'], 10, ',', '.'), 20, ' ', STR_PAD_LEFT),
                ),
                $content
            );
        }

        $output->writeln($content);
    }
}
