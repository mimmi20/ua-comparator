#!/usr/bin/env php
<?php


use Cache\Adapter\PHPArray\ArrayCachePool;
use Wurfl\Configuration\FileConfig;
use Wurfl\Manager;
use Wurfl\Storage\Storage;
use Cache\Adapter\Filesystem\FilesystemCachePool;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;
use Noodlehaus\Config;

chdir(dirname(__DIR__));

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

$config = new Config(['data/configs/config.json']);

if (!$config['modules']['wurfl']['enabled']) {
    exit;
}

$adapter     = new Local('data/cache/wurfl/');
$fileCache   = new FilesystemCachePool(new Filesystem($adapter));
$memoryCache = new ArrayCachePool();

$wurflConfig        = new FileConfig('data/configs/wurfl-config.xml');
$cacheStorage       = new Storage($memoryCache);
$persistenceStorage = new Storage($fileCache);
$wurflManager       = new Manager($wurflConfig, $persistenceStorage, $cacheStorage);

$wurflManager->getAllDevicesID();

try {
    $device = $wurflManager->getDeviceForUserAgent('abcdef');

    $device->getAllCapabilities();
} catch (\Exception $e) {
    //
}
