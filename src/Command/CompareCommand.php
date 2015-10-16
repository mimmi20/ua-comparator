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
use Monolog\Processor\MemoryUsageProcessor;
use PDO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use UaComparator\Helper\Check;
use UaComparator\Helper\LineHandler;
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
            'Wurfl',
            'Browscap',
            'CrossJoin',
            'Piwik',
            'UaParser',
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
        $loggerHelper = new LoggerHelper();
        $logger       = $loggerHelper->create();

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
            ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(
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

            $detectorModule = new BrowserDetectorModule($logger, new File(array('dir' => 'data/cache/browser/')));
            $detectorModule->setId(0)->setName('BrowserDetector');

            $collection->addModule($detectorModule);

            $output->writeln(
                ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(
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
                ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(
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

            $browscapModule = new Browscap($logger, new File(array('dir' => 'data/cache/browscap/')), $iniFile);
            $browscapModule->setId(9)->setName('Browscap-PHP');

            $collection->addModule($browscapModule);

            $output->writeln(
                ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(
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

            $crossjoinModule = new CrossJoin($logger, new File(array('dir' => 'data/cache/crossjoin/')), $iniFile);
            $crossjoinModule->setId(10)->setName('Crossjoin\Browscap');

            $collection->addModule($crossjoinModule);

            $output->writeln(
                ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(
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

            $uaparserModule = new UaParser($logger, new Memory());
            $uaparserModule->setId(5)->setName('UAParser');

            $collection->addModule($uaparserModule);

            $output->writeln(
                ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(
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
         * $uasparserModule = new UasParser($logger, new Memory());
         * $uasparserModule
         * ->setId(6)
         * ->setName('UASParser')
         * ;
         *
         * $collection->addModule($uasparserModule);
         *
         * $output->writeln(' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes');
         * }
         */

        /*******************************************************************************
         * WURFL - PHP 5.3 port
         */

        if (in_array('Wurfl', $modules)) {
            $output->write('initializing Wurfl API (PHP-API 5.3 port) ...', false);

            ini_set('max_input_time', '6000');
            $wurflModule = new Wurfl($logger, new File(array('dir' => 'data/cache/wurfl/')), 'data/wurfl-config.xml');
            $wurflModule->setId(11)->setName('WURFL API (PHP-API 5.3)');

            $collection->addModule($wurflModule);

            $output->writeln(
                ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(
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
                new File(array('dir' => 'data/cache/wurfl_old/')),
                'data/wurfl-config.xml'
            );
            $oldWurflModule->setId(7)->setName('WURFL API (PHP-API 5.2 original)');

            $collection->addModule($oldWurflModule);

            $output->writeln(
                ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(
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
                ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(
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
            'ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(
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

        $lineHandler = new LineHandler();

        foreach ($source->getUserAgents($logger) as $line) {
            try {
                $lineHandler->handleLine($line, $collection, $messageFormatter, $i, $checks);
                continue;
            } catch (\Exception $e) {
                if (1 === $e->getCode()) {
                    $nokfound++;
                } elseif (2 === $e->getCode()) {
                    $sosofound++;
                } else {
                    $okfound++;
                }

                $content = str_replace(
                    array(
                        '#count#',
                        '#plus#',
                        '#minus#',
                        '#soso#',
                        '#percent1#',
                        '#percent2#',
                        '#percent3#',
                    ),
                    array(
                        str_pad(number_format(0, 0, ',', '.'), FIRST_COL_LENGTH - 7, ' ', STR_PAD_LEFT),
                        str_pad($okfound, FIRST_COL_LENGTH - 11, ' ', STR_PAD_LEFT),
                        str_pad($nokfound, FIRST_COL_LENGTH - 11, ' ', STR_PAD_LEFT),
                        str_pad($sosofound, FIRST_COL_LENGTH - 11, ' ', STR_PAD_LEFT),
                        str_pad(
                            number_format((100 * $okfound / $i), 9, ',', '.'),
                            FIRST_COL_LENGTH - 4,
                            ' ',
                            STR_PAD_LEFT
                        ),
                        str_pad(
                            number_format((100 * $nokfound / $i), 9, ',', '.'),
                            FIRST_COL_LENGTH - 4,
                            ' ',
                            STR_PAD_LEFT
                        ),
                        str_pad(
                            number_format((100 * $sosofound / $i), 9, ',', '.'),
                            FIRST_COL_LENGTH - 4,
                            ' ',
                            STR_PAD_LEFT
                        ),
                    ),
                    $e->getMessage()
                );

                $output->write($content, false);
            }

            $i++;
        }

        $output->writeln('');
        $output->writeln(
            str_repeat('-', FIRST_COL_LENGTH) . '+' . str_repeat('-', $collection->count() - 1) . '+' . str_repeat(
                '-',
                $aLength
            )
        );

        $content = '#plus# + detected|' . "\n" . '#percent1# % +|' . "\n" . '#minus# - detected|' . "\n" . '#percent2# % -|' . "\n" . '#soso# : detected|' . "\n" . '#percent3# % :|' . "\n";

        --$i;

        if ($i < 1) {
            $i = 1;
        }

        $content = str_replace(
            array(
                '#plus#',
                '#minus#',
                '#soso#',
                '#percent1#',
                '#percent2#',
                '#percent3#',
            ),
            array(
                substr(str_repeat(' ', FIRST_COL_LENGTH) . $okfound, -(FIRST_COL_LENGTH - 11)),
                substr(str_repeat(' ', FIRST_COL_LENGTH) . $nokfound, -(FIRST_COL_LENGTH - 11)),
                substr(str_repeat(' ', FIRST_COL_LENGTH) . $sosofound, -(FIRST_COL_LENGTH - 11)),
                substr(
                    str_repeat(' ', FIRST_COL_LENGTH) . number_format((100 * $okfound / $i), 9, ',', '.'),
                    -(FIRST_COL_LENGTH - 4)
                ),
                substr(
                    str_repeat(' ', FIRST_COL_LENGTH) . number_format((100 * $nokfound / $i), 9, ',', '.'),
                    -(FIRST_COL_LENGTH - 4)
                ),
                substr(
                    str_repeat(' ', FIRST_COL_LENGTH) . number_format((100 * $sosofound / $i), 9, ',', '.'),
                    -(FIRST_COL_LENGTH - 4)
                ),
            ),
            $content
        );

        $output->writeln(
            substr(str_repeat(' ', FIRST_COL_LENGTH) . $i . '/' . $count, -1 * FIRST_COL_LENGTH) . '|' . "\n" . $content
        );
    }
}
