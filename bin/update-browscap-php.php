#!/usr/bin/env php
<?php
/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */
chdir(dirname(__DIR__));

$autoloadPaths = array(
    'vendor/autoload.php',
    '../../autoload.php',
);

foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

ini_set('memory_limit', '-1');

use Noodlehaus\Config;
use BrowscapPHP\Browscap as Browscap3;
use WurflCache\Adapter\File;

$bench = new Ubench;
$bench->start();

echo ' updating cache for BrowscapPHP\Browscap', PHP_EOL;

$config   = new Config(['data/configs/config.json']);
$cacheDir = $config['modules']['browscap3']['cache-dir'];

$browscap = new Browscap3();
$cache = new File([File::DIR => $cacheDir]);
$browscap->setCache($cache);
$browscap->update();

$bench->end();
echo ' ', $bench->getTime(true), ' secs ', PHP_EOL;
echo ' ', number_format($bench->getMemoryPeak(true)), ' bytes', PHP_EOL;
