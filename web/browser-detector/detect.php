<?php
/**
 * This file is part of the ua-comparator package.
 *
 * Copyright (c) 2015-2017, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

use BrowserDetector\Detector;
use Monolog\ErrorHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\MemoryPeakUsageProcessor;
use Monolog\Processor\MemoryUsageProcessor;

chdir(dirname(dirname(__DIR__)));

$autoloadPaths = [
    'vendor/autoload.php',
    '../../autoload.php',
];

foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

ini_set('memory_limit', '-1');

header('Content-Type: x-application/serialize', true);

$logger = new Logger('ua-comparator');

$stream = new StreamHandler('log/error-browser-detector.log', Logger::ERROR);
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

$browscapAdapter = new \League\Flysystem\Local\LocalFilesystemAdapter('data/cache/browser/');
$cache   = new \MatthiasMullie\Scrapbook\Psr16\SimpleCache(
    new \MatthiasMullie\Scrapbook\Adapters\Flysystem(
        new \League\Flysystem\Filesystem($browscapAdapter)
    )
);

$start = microtime(true);

$factory = new \BrowserDetector\DetectorFactory($cache, $logger);
$parser  = $factory();

try {
    /** @var \UaResult\Result\Result $detectionResult */
    $detectionResult = $parser->getBrowser($_GET['useragent'], true);
} catch (\Exception $e) {
    $logger->critical($e);
    $detectionResult = null;
}

$duration = microtime(true) - $start;

echo htmlentities(serialize(
    [
        'result' => $detectionResult->toArray(),
        'duration' => $duration,
        'memory' => memory_get_usage(true),
    ]
));
