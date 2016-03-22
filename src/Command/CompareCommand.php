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
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\MemoryPeakUsageProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use UaComparator\Helper\Check;
use UaComparator\Helper\MessageFormatter;

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

    /**
     * Configures the current command.
     */
    protected function configure()
    {
        $allChecks = [
            Check::MINIMUM,
            Check::MEDIUM,
        ];

        $this
            ->setName('compare')
            ->setDescription('compares the results of different useragent parsers')
            ->addOption(
                'check-level',
                '-c',
                InputOption::VALUE_REQUIRED,
                'the level for the checks to do. Available Options:' . implode(',', $allChecks),
                Check::MINIMUM
            )
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

        echo 'initializing App ...';

        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', 0);
        ini_set('max_input_time', 0);
        ini_set('display_errors', 1);
        ini_set('error_log', './error.log');
        error_reporting(E_ALL | E_DEPRECATED);

        date_default_timezone_set('Europe/Berlin');
        setlocale(LC_CTYPE, 'de_DE@euro', 'de_DE', 'de', 'ge');

        echo ' - ready', "\n";

        /*******************************************************************************
         * Loop
         */

        $dataDir   = 'data/results/';
        $iterator  = new \DirectoryIterator($dataDir);
        $i         = 1;
        $okfound   = 0;
        $nokfound  = 0;
        $sosofound = 0;

        $messageFormatter = new MessageFormatter();
        $messageFormatter->setColumnsLength(self::COL_LENGTH);

        $checklevel  = $input->getOption('check-level');
        $checkHelper = new Check();
        $checks      = $checkHelper->getChecks($checklevel);

        foreach (new \IteratorIterator($iterator) as $file) {
            /** @var $file \SplFileInfo */
            if ($file->isFile() || in_array($file->getFilename(), array('.', '..'))) {
                continue;
            }

            $path          = $file->getPathname();
            $innerIterator = new \DirectoryIterator($path);
            $agent         = null;

            $collection = [];

            foreach (new \IteratorIterator($innerIterator) as $innerFile) {
                /** @var $innerFile \SplFileInfo */
                if (!$innerFile->isFile() || 'bench.txt' === $innerFile->getFilename()) {
                    continue;
                }

                $moduleName = $innerFile->getBasename('.txt');

                $collection[$moduleName] = unserialize(file_get_contents($innerFile->getPathname()));

                if (null === $agent) {
                    $agent = $collection[$moduleName]['ua'];
                }
            }
            
            unset($innerIterator);

            $messageFormatter->setCollection($collection);

            /*
             * Auswertung
             */
            $allResults = [];
            $matches = [];

            foreach ($checks as $propertyTitel => $x) {
                if (empty($x['key'])) {
                    $propertyName = $propertyTitel;
                } else {
                    $propertyName = $x['key'];
                }

                $detectionResults = $messageFormatter->formatMessage($propertyName);

                foreach ($detectionResults as $result) {
                    $matches[] = substr($result, 0, 1);
                }

                $allResults[$propertyTitel] = $detectionResults;
            }

            if (in_array('-', $matches)) {
                ++$nokfound;

                $content = file_get_contents('src/templates/single-line.txt');
                $content = str_replace('#ua#', $agent, $content);
                $content = str_replace(
                    '#               id#',
                    str_pad($i, self::FIRST_COL_LENGTH - 1, ' ', STR_PAD_LEFT),
                    $content
                );

                $timeSummary = 0.0;

                foreach ($collection as $moduleName => $data) {
                    $content = str_replace(
                        '#' . $moduleName . '#',
                        str_pad(number_format($data['time'], 10, ',', '.'), 20, ' ', STR_PAD_LEFT),
                        $content
                    );

                    $timeSummary += (float) $data['time'];
                }

                $content = str_replace(
                    '#TimeSummary#',
                    str_pad(number_format($timeSummary, 10, ',', '.'), 20, ' ', STR_PAD_LEFT),
                    $content
                );

                $content .= $this->getLine($collection);

                $content .= '|                    |' . str_repeat(' ', count($collection)) . '|                                                  |';
                foreach (array_keys($collection) as $moduleName) {
                    $content .= str_pad($moduleName, self::COL_LENGTH, ' ') . '|';
                }
                $content .= "\n";

                $content .= $this->getLine($collection);

                foreach ($allResults as $propertyTitel => $detectionResults) {
                    $lineContent = '|                    |' . str_repeat(' ', count($collection)) . '|'
                        . str_pad($propertyTitel, self::COL_LENGTH, ' ', STR_PAD_LEFT)
                        . '|';

                    foreach (array_values($detectionResults) as $index => $value) {
                        $lineContent .= str_pad($value, self::COL_LENGTH, ' ') . '|';
                        $lineContent = substr_replace($lineContent, substr($value, 0, 1), 22 + $index, 1);
                    }

                    $content .= $lineContent .  "\n";
                }

                $content .= $this->getLine($collection);

                $content .= '-';
                $content = str_replace(
                    [
                        '#  plus#',
                        '# minus#',
                        '#  soso#',
                        '#     percent1#',
                        '#     percent2#',
                        '#     percent3#',
                    ],
                    [
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
                    ],
                    $content
                );

                echo preg_replace('/\#[^#]*\#/', '               (n/a)', $content);
            } elseif (in_array(':', $matches)) {
                echo ':';
                ++$sosofound;
            } else {
                echo '.';
                ++$okfound;
            }

            if (($i % 100) === 0) {
                echo "\n";
            }
            
            unset($collection, $allResults, $matches);

            ++$i;
        }
    }

    /**
     * @param array $collection
     *
     * @return string
     */
    private function getLine(array $collection = [])
    {
        $content  = '+--------------------+';
        $content .= str_repeat('-', count($collection));
        $content .= '+--------------------------------------------------+';
        $content .= str_repeat('--------------------------------------------------+', count($collection));
        $content .= "\n";

        return $content;
    }
}
