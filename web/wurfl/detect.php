<?php


use Cache\Adapter\Filesystem\FilesystemCachePool;
use Cache\Adapter\PHPArray\ArrayCachePool;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Monolog\ErrorHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\MemoryPeakUsageProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Wurfl\Configuration\FileConfig;
use Wurfl\Manager;
use Wurfl\Storage\Storage;
use Wurfl\VirtualCapability\VirtualCapabilityProvider;

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

header('Content-Type: application/json', true);

$logger = new Logger('ua-comparator');

$stream = new StreamHandler('log/error-wurfl.log', Logger::ERROR);
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

$start = microtime(true);

$adapter     = new Local('data/cache/wurfl/');
$fileCache   = new FilesystemCachePool(new Filesystem($adapter));
$memoryCache = new ArrayCachePool();

$wurflConfig        = new FileConfig('data/configs/wurfl-config.xml');
$cacheStorage       = new Storage($memoryCache);
$persistenceStorage = new Storage($fileCache);
$wurflManager       = new Manager($wurflConfig, $persistenceStorage, $cacheStorage);

$device = $wurflManager->getDeviceForUserAgent($_GET['useragent']);

$duration = microtime(true) - $start;

$result = [];

foreach ($device->getAllCapabilities() as $capability => $value) {
    $result[$capability] = $value;
}

foreach ($device->getAllVirtualCapabilities() as $capability => $value) {
    $result[VirtualCapabilityProvider::PREFIX_CONTROL . $capability] = $value;
}

echo htmlentities(json_encode(
    [
        'result'   => $result,
        'duration' => $duration,
        'memory'   => memory_get_usage(true),
    ]
));
