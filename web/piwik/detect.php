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

use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\Client\Browser;
use DeviceDetector\Parser\Device\DeviceParserAbstract;
use DeviceDetector\Parser\OperatingSystem;

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

DeviceParserAbstract::setVersionTruncation(DeviceParserAbstract::VERSION_TRUNCATION_NONE);

header('Content-Type: application/json', true);

$start          = microtime(true);
$deviceDetector = new DeviceDetector($_GET['useragent']);
$deviceDetector->parse();

$os       = $deviceDetector->getOs();
$osFamily = OperatingSystem::getOsFamily($deviceDetector->getOs('short_name'));

$client        = $deviceDetector->getClient();
$browserFamily = Browser::getBrowserFamily($deviceDetector->getClient('short_name'));

$processed = [
    'user_agent' => $deviceDetector->getUserAgent(),
    'bot'        => ($deviceDetector->isBot() ? $deviceDetector->getBot() : false),
    'os'         => [
        'name'    => (isset($os['name']) ? $os['name'] : ''),
        'version' => (isset($os['version']) ? $os['version'] : null),
    ],
    'client' => [
        'name'    => (isset($client['name']) ? $client['name'] : ''),
        'version' => (isset($client['version']) ? $client['version'] : null),
        'engine'  => (isset($client['engine']) ? $client['engine'] : null),
    ],
    'device' => [
        'type'  => $deviceDetector->getDeviceName(),
        'brand' => $deviceDetector->getBrand(),
        'model' => $deviceDetector->getModel(),
    ],
    'os_family'      => $osFamily !== false ? $osFamily : 'Unknown',
    'browser_family' => $browserFamily !== false ? $browserFamily : 'Unknown',
];
$duration = microtime(true) - $start;

echo json_encode(
    [
        'result'   => $processed,
        'duration' => $duration,
        'memory'   => memory_get_usage(true),
    ]
);
