<?php
/**
 * This file is part of the mimmi20/ua-comparator package.
 *
 * Copyright (c) 2015-2023, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace UaComparator\Command;

use DirectoryIterator;
use IteratorIterator;
use LogicException;
use Monolog\Handler\PsrHandler;
use Monolog\Logger;
use Noodlehaus\Config;
use Psr\Cache\CacheItemPoolInterface;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use UaComparator\Helper\Check;
use UaComparator\Helper\MessageFormatter;

use function array_keys;
use function array_values;
use function assert;
use function count;
use function file_exists;
use function file_get_contents;
use function in_array;
use function json_decode;
use function mb_substr;
use function str_pad;
use function str_repeat;
use function substr_replace;

use const JSON_THROW_ON_ERROR;
use const STR_PAD_LEFT;

final class CompareCommand extends Command
{
    public const COL_LENGTH       = 50;
    public const FIRST_COL_LENGTH = 20;

    public function __construct(private Logger $logger, private CacheItemPoolInterface $cache, private Config $config)
    {
        parent::__construct();
    }

    /**
     * Configures the current command.
     */
    protected function configure(): void
    {
        $this
            ->setName('compare')
            ->setDescription('compares the results of different useragent parsers');
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
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('preparing App ...');

        $consoleLogger = new ConsoleLogger($output);
        $this->logger->pushHandler(new PsrHandler($consoleLogger));

        /*
         * Loop
         */

        $dataDir   = 'data/results/';
        $iterator  = new DirectoryIterator($dataDir);
        $i         = 1;
        $okfound   = 0;
        $nokfound  = 0;
        $sosofound = 0;

        $messageFormatter = new MessageFormatter();
        $messageFormatter->setColumnsLength(self::COL_LENGTH);

        $output->writeln('init checks ...');

        $checkHelper = new Check();
        $checks      = $checkHelper->getChecks();

        $output->writeln('init modules ...');

        $modules = [];

        foreach ($this->config['modules'] as $moduleConfig) {
            if (!$moduleConfig['enabled'] || !$moduleConfig['name'] || !$moduleConfig['class']) {
                continue;
            }

            $modules[] = $moduleConfig['name'];
        }

        foreach (new IteratorIterator($iterator) as $file) {
            assert($file instanceof SplFileInfo);
            if ($file->isFile() || in_array($file->getFilename(), ['.', '..'], true)) {
                continue;
            }

            $path  = $file->getPathname();
            $agent = null;

            $collection = [];

            foreach ($modules as $module) {
                if (file_exists($path . '/' . $module . '.json')) {
                    $collection[$module] = json_decode(file_get_contents($path . '/' . $module . '.json'), true, 512, JSON_THROW_ON_ERROR);

                    if (null === $agent) {
                        $agent = $collection[$module]['ua'];
                    }
                } else {
                    $collection[$module] = ['result' => []];
                }
            }

            $messageFormatter->setCollection($collection);

            /*
             * Auswertung
             */
            $allResults = [];
            $matches    = [];

            foreach ($checks as $propertyTitel => $x) {
                if (empty($x['key'])) {
                    $propertyName = $propertyTitel;
                } else {
                    $propertyName = $x['key'];
                }

                $detectionResults = $messageFormatter->formatMessage($propertyName, $this->cache, $this->logger);

                foreach ($detectionResults as $result) {
                    $matches[] = mb_substr($result, 0, 1);
                }

                $allResults[$propertyTitel] = $detectionResults;
            }

            if (in_array('-', $matches, true)) {
                ++$nokfound;

                $content  = $this->getLine($collection);
                $content .= '|                    |' . mb_substr($agent, 0, self::COL_LENGTH * count($collection)) . "\n";

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
                        $lineContent  = substr_replace($lineContent, mb_substr($value, 0, 1), 22 + $index, 1);
                    }

                    $content .= $lineContent . "\n";
                }

                $content .= $this->getLine($collection);
                echo '-', "\n", $content;
            } elseif (in_array(':', $matches, true)) {
                echo ':';
                ++$sosofound;
            } else {
                echo '.';
                ++$okfound;
            }

            if (0 === $i % 100) {
                echo "\n";
            }

            unset($collection, $allResults, $matches);

            ++$i;
        }

        return self::SUCCESS;
    }

    private function getLine(array $collection = []): string
    {
        $content  = '+--------------------+';
        $content .= str_repeat('-', count($collection));
        $content .= '+--------------------------------------------------+';
        $content .= str_repeat('--------------------------------------------------+', count($collection));
        $content .= "\n";

        return $content;
    }
}
