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
use BrowscapHelper\Source\DirectorySource;
use BrowscapHelper\Source\MatomoSource;
use BrowscapHelper\Source\OutputAwareInterface;
use BrowscapHelper\Source\UapCoreSource;
use BrowscapHelper\Source\WhichBrowserSource;
use BrowscapHelper\Source\WootheeSource;
use JsonException;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use LogicException;
use MatthiasMullie\Scrapbook\Adapters\Flysystem;
use MatthiasMullie\Scrapbook\Adapters\MemoryStore;
use MatthiasMullie\Scrapbook\Psr6\Pool;
use Monolog\Handler\PsrHandler;
use Monolog\Logger;
use Noodlehaus\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use UaComparator\Module\Check\DefaultCheck;
use UaComparator\Module\Http;
use UaComparator\Module\Mapper\DefaultMapper;
use UaComparator\Module\ModuleCollection;
use UaComparator\Module\ModuleInterface;
use UaDataMapper\InputMapper;
use UnexpectedValueException;

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
    /** @throws \Symfony\Component\Console\Exception\LogicException */
    public function __construct(private readonly Logger $logger, private readonly Config $config)
    {
        parent::__construct();
    }

    /**
     * Configures the current command.
     *
     * @throws InvalidArgumentException
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
     * @return int null or 0 if everything went fine, or an error code
     *
     * @throws LogicException When this abstract method is not implemented
     * @throws JsonException
     * @throws UnexpectedValueException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('preparing App ...');

        $consoleLogger = new ConsoleLogger($output);
        $this->logger->pushHandler(new PsrHandler($consoleLogger));

        $output->writeln('preparing modules ...');

        $collection = new ModuleCollection();

        /*
         * BrowserDetector
         */

        $inputMapper = new InputMapper();

        foreach ($this->config['modules'] as $moduleConfig) {
            assert(is_array($moduleConfig));

            if (!$moduleConfig['enabled'] || !$moduleConfig['name']) {
                continue;
            }

            $output->writeln('    preparing module ' . $moduleConfig['name'] . ' ...');

            if (!isset($moduleConfig['requires-cache'])) {
                $moduleCache = new Pool(
                    new MemoryStore(),
                );
            } elseif ($moduleConfig['requires-cache'] && isset($moduleConfig['cache-dir'])) {
                $adapter     = new LocalFilesystemAdapter($moduleConfig['cache-dir']);
                $moduleCache = new Pool(
                    new Flysystem(
                        new Filesystem($adapter),
                    ),
                );
            } else {
                $moduleCache = new Pool(
                    new MemoryStore(),
                );
            }

            $collection->addModule(
                new Http(
                    name: $moduleConfig['name'],
                    logger: $this->logger,
                    cache: $moduleCache,
                    check: new DefaultCheck(),
                    mapper: new DefaultMapper($inputMapper),
                    config: $moduleConfig['request'],
                ),
            );
        }

        /*
         * init Modules
         */

        $output->writeln('initializing modules ...');

        foreach ($collection as $module) {
            /** @var ModuleInterface $module */
            $output->writeln('    initializing module ' . $module->getName() . ' ...');

            $module->init();
        }

        /*
         * initialize Source
         */

        $output->writeln('initializing sources ...');

        $sources = [
            new BrowscapSource(),
            new MatomoSource(),
            new UapCoreSource(),
            new WhichBrowserSource(),
            new WootheeSource(),
            new BrowserDetectorSource(),
            new DirectorySource('data/useragents'),
        ];

        /*
         * Loop
         */

        $output->writeln('start Loop ...');

        $limit         = (int) $input->getOption('limit');
        $counter       = 1;
        $existingTests = [];

        foreach ($sources as $source) {
            if ($source instanceof OutputAwareInterface) {
                $source->setOutput($output);
            }

            $baseMessage = sprintf('reading from source %s ', $source->getName());

            if (!$source->isReady($baseMessage)) {
                continue;
            }

            foreach ($source->getHeaders($baseMessage) as $headers) {
                $agent = $headers['user-agent'] ?? 'n/a';
                $agent = trim((string) $agent);

                if (isset($existingTests[$agent])) {
                    continue;
                }

                if ($limit > 0) {
                    if ($counter > $limit) {
                        continue;
                    }

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
                        ->startBenchmark()
                        ->detect($agent, $headers)
                        ->endBenchmark();

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
        }

        return self::SUCCESS;
    }
}
