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

namespace UaComparator\Helper;

use Monolog\ErrorHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\MemoryPeakUsageProcessor;
use Monolog\Processor\MemoryUsageProcessor;

use function assert;
use function is_callable;

final class LoggerFactory
{
    /**
     * creates a \Monolo\Logger instance
     *
     * @param bool $debug If true the debug logging mode will be enabled
     *
     * @throws void
     */
    public static function create(bool $debug = false): Logger
    {
        $logger = new Logger('ua-comparator');

        if ($debug) {
            $stream = new StreamHandler('php://output', Logger::DEBUG);
            $stream->setFormatter(
                new LineFormatter('[%datetime%] %channel%.%level_name%: %message% %extra%' . "\n"),
            );
        } else {
            $stream = new StreamHandler('php://output', Logger::INFO);
            $stream->setFormatter(new LineFormatter('[%datetime%] %message% %extra%' . "\n"));
        }

        $logger->pushHandler(new StreamHandler('log/error.log', Logger::NOTICE));

        $peakMemoryProcessor = new MemoryPeakUsageProcessor(true);
        assert(is_callable($peakMemoryProcessor));
        $logger->pushProcessor($peakMemoryProcessor);

        $memoryProcessor = new MemoryUsageProcessor(true);
        assert(is_callable($memoryProcessor));
        $logger->pushProcessor($memoryProcessor);

        $logger->pushHandler($stream);

        ErrorHandler::register($logger);

        return $logger;
    }
}
