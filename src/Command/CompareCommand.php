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

use Monolog\Handler\PsrHandler;
use Monolog\Logger;
use Noodlehaus\Config;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use UaComparator\Helper\Check;
use UaComparator\Helper\MessageFormatter;

/**
 * Class CompareCommand
 *
 * @category   UaComparator
 *
 * @author     Thomas Müller <mimmi20@live.de>
 */
class CompareCommand extends Command
{
    const COL_LENGTH       = 50;
    const FIRST_COL_LENGTH = 20;

    private Logger $logger;

    private CacheItemPoolInterface $cache;

    private Config $config;

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
    ): int {
        $output->writeln('preparing App ...');

        $consoleLogger = new ConsoleLogger($output);
        $this->logger->pushHandler(new PsrHandler($consoleLogger));

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

        foreach (new \IteratorIterator($iterator) as $file) {
            /** @var $file \SplFileInfo */
            if ($file->isFile() || in_array($file->getFilename(), ['.', '..'])) {
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

            if (in_array('-', $matches)) {
                ++$nokfound;

                $content = $this->getLine($collection);
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
                        $lineContent = substr_replace($lineContent, mb_substr($value, 0, 1), 22 + $index, 1);
                    }

                    $content .= $lineContent . "\n";
                }

                $content .= $this->getLine($collection);
                echo '-', "\n", $content;
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

        return self::SUCCESS;
    }

    /**
     * @param array $collection
     *
     * @return string
     */
    private function getLine(array $collection = []): string
    {
        $content = '+--------------------+';
        $content .= str_repeat('-', count($collection));
        $content .= '+--------------------------------------------------+';
        $content .= str_repeat('--------------------------------------------------+', count($collection));
        $content .= "\n";

        return $content;
    }
}
