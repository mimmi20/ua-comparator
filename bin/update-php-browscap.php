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
use phpbrowscap\Browscap as Browscap2;

$bench = new Ubench;
$bench->start();

echo ' updating cache for phpbrowscap\Browscap', PHP_EOL;

$config   = new Config(['data/configs/config.dist.json', '?data/configs/config.json']);
$cacheDir = $config['modules']['browscap2']['cache-dir'];

$browscap = new Browscap2($cacheDir);
$browscap->localFile    = realpath('data/browser/full_php_browscap.ini');
$browscap->updateMethod = Browscap2::UPDATE_LOCAL;
$browscap->updateCache();

$bench->end();
echo ' ', $bench->getTime(true), ' secs ', PHP_EOL;
echo ' ', number_format($bench->getMemoryPeak(true)), ' bytes', PHP_EOL;
