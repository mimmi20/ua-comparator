<?php


namespace UaComparator\Helper;

use Monolog\ErrorHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\MemoryPeakUsageProcessor;
use Monolog\Processor\MemoryUsageProcessor;

/**
 * Class LoggerHelper
 *
 * @category   Browscap
 *
 * @author     Thomas Müller <mimmi20@live.de>
 */
class LoggerFactory
{
    /**
     * creates a \Monolo\Logger instance
     *
     * @param bool $debug If true the debug logging mode will be enabled
     *
     * @return \Monolog\Logger
     */
    public static function create($debug = false)
    {
        $logger = new Logger('ua-comparator');

        if ($debug) {
            $stream = new StreamHandler('php://output', Logger::DEBUG);
            $stream->setFormatter(
                new LineFormatter('[%datetime%] %channel%.%level_name%: %message% %extra%' . "\n")
            );
        } else {
            $stream = new StreamHandler('php://output', Logger::INFO);
            $stream->setFormatter(new LineFormatter('[%datetime%] %message% %extra%' . "\n"));
        }

        $logger->pushHandler(new StreamHandler('log/error.log', Logger::NOTICE));

        /** @var callable $peakMemoryProcessor */
        $peakMemoryProcessor = new MemoryPeakUsageProcessor(true);
        $logger->pushProcessor($peakMemoryProcessor);

        /** @var callable $memoryProcessor */
        $memoryProcessor = new MemoryUsageProcessor(true);
        $logger->pushProcessor($memoryProcessor);

        $logger->pushHandler($stream);

        ErrorHandler::register($logger);

        return $logger;
    }
}
