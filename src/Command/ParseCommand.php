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

use Monolog\ErrorHandler;
use Monolog\Formatter\LineFormatter;
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
use UaComparator\Helper\TimeFormatter;
use UaComparator\Module\ModuleCollection;
use UaComparator\Source\DirectorySource;
use UaComparator\Source\PdoSource;
use UaComparator\Source\TestsSource;
use UaDataMapper\InputMapper;
use WurflCache\Adapter\File;
use WurflCache\Adapter\Memory;

/**
 * Class CompareCommand
 *
 * @category   UaComparator
 *
 * @author     Thomas Müller <t_mueller_stolzenhain@yahoo.de>
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
        $config = new Config(['data/configs/config.dist.json', '?data/configs/config.json']);

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
                'source',
                '-s',
                InputOption::VALUE_REQUIRED,
                'the source for the useragents to parse, possible values are: "' . self::SOURCE_SQL . '", "'
                . self::SOURCE_DIR . '" and "' . self::SOURCE_TEST . '"',
                self::SOURCE_TEST
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
        $startTime = microtime(true);
        $logger    = new Logger('ua-comparator');

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

        $output->write('preparing App ...', false);

        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', 0);
        ini_set('max_input_time', 0);
        ini_set('display_errors', 1);
        ini_set('error_log', './error.log');
        error_reporting(E_ALL | E_DEPRECATED);

        date_default_timezone_set('Europe/Berlin');
        setlocale(LC_CTYPE, 'de_DE@euro', 'de_DE', 'de', 'ge');

        $output->writeln(
            ' - ready ' . TimeFormatter::formatTime(microtime(true) - $startTime) . ' - ' . number_format(
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

                if ($key === $module) {
                    $output->write('preparing ' . $moduleConfig['name'] . ' ...', false);

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
                    $mapper = new $mapperName();
                    $mapper->setMapper($inputMapper);
                    $detectorModule->setMapper($mapper);

                    $collection->addModule($detectorModule);

                    $output->writeln(
                        ' - ready ' . TimeFormatter::formatTime(microtime(true) - $startTime) . ' - ' . number_format(
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
         * init Modules
         */

        foreach ($collection->getModules() as $module) {
            $output->write('initializing ' . $module->getName() . ' ...', false);

            $module->init();

            $output->writeln(
                ' - ready ' . TimeFormatter::formatTime(microtime(true) - $startTime) . ' - ' . number_format(
                    memory_get_usage(true),
                    0,
                    ',',
                    '.'
                ) . ' Bytes'
            );
        }

        /*******************************************************************************
         * initialize Source
         */

        $output->write('initializing Source ...', false);

        $sourceOption = $input->getOption('source');

        switch ($sourceOption) {
            case self::SOURCE_SQL:
                $dsn      = 'mysql:dbname=browscap;host=localhost';
                $user     = 'root';
                $password = '';

                $adapter = new PDO(
                    $dsn,
                    $user,
                    $password,
                    [
                        1002 => 'SET NAMES \'UTF8\'', // PDO::MYSQL_ATTR_INIT_COMMAND
                        1005 => 1024 * 1024 * 50,     // PDO::MYSQL_ATTR_MAX_BUFFER_SIZE
                    ]
                );
                $adapter->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $source = new PdoSource($adapter);
                break;
            case self::SOURCE_DIR:
                $uaSourceDirectory = 'data/useragents';
                $source            = new DirectorySource($uaSourceDirectory);
                break;
            case self::SOURCE_TEST:
            default:
                $source = new TestsSource();
                break;
        }

        $output->writeln(
            ' - ready ' . TimeFormatter::formatTime(microtime(true) - $startTime) . ' - ' . number_format(
                memory_get_usage(true),
                0,
                ',',
                '.'
            ) . ' Bytes'
        );

        $limit = (int) $input->getOption('limit');
        $i     = 1;

        /*******************************************************************************
         * Loop
         */

        $output->writeln('start Loop ...');

        foreach ($source->getUserAgents($logger, $limit) as $agent) {
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
                    'data/results/' . $cacheId . '/' . $module->getName() . '.txt',
                    serialize(
                        [
                            'ua'     => $agent,
                            'result' => $detectionResult,
                            'time'   => $actualTime,
                            'memory' => $actualMemory,
                        ]
                    )
                );
            }

            file_put_contents(
                'data/results/' . $cacheId . '/bench.json',
                json_encode($bench, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT)
            );

            echo '.';

            if (($i % 100) === 0) {
                echo "\n";
            }

            ++$i;
        }

        echo "\n";
    }
}
