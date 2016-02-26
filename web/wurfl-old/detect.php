<?php
/**
 * Copyright (c) 2015, Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
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
 * @author    Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 * @copyright 2015 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 *
 * @link      https://github.com/mimmi20/ua-comparator
 */

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

header('Content-Type: application/json', true);

$start    = microtime(true);
// Create WURFL Configuration from an XML config file
$wurflConfigOrig  = new \WURFL_Configuration_XmlConfig($this->configFile);
$wurflCacheOrig   = new \WURFL_Storage_Memory();
$wurflStorageOrig = new \WURFL_Storage_File([\WURFL_Storage_File::DIR => 'data/cache/wurfl_old/']);

// Create a WURFL Manager Factory from the WURFL Configuration
$wurflManagerFactoryOrig = new \WURFL_WURFLManagerFactory($wurflConfigOrig, $wurflStorageOrig, $wurflCacheOrig);
ini_set('max_input_time', '6000');
// Create a WURFL Manager
$wurflManagerOrig = $wurflManagerFactoryOrig->create();

$device = $wurflManagerOrig->getDeviceForUserAgent($_POST['useragent']);

$duration = microtime(true) - $start;

echo json_encode(
    [
        'result'   => $device->getAllCapabilities(),
        'duration' => $duration,
        'memory'   => memory_get_usage(true),
    ]
);
