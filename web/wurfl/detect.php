<?php
/**
 * Copyright (c) 2015, Thomas Mueller <mimmi20@live.de>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @category  UaComparator
 *
 * @author    Thomas Mueller <mimmi20@live.de>
 * @copyright 2015 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 *
 * @link      https://github.com/mimmi20/ua-comparator
 */

use Cache\Adapter\Filesystem\FilesystemCachePool;
use Cache\Adapter\PHPArray\ArrayCachePool;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
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

echo json_encode(
    [
        'result'   => $result,
        'duration' => $duration,
        'memory'   => memory_get_usage(true),
    ]
);
