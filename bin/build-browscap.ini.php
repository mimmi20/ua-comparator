#!/usr/bin/env php
<?php
/**
 * Copyright (c) 2015-2017, Thomas Mueller <mimmi20@live.de>
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
 * @copyright 2015-2017 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 *
 * @link      https://github.com/mimmi20/ua-comparator
 */

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

use Browscap\Generator\BuildGenerator;
use Browscap\Helper\CollectionCreator;
use Browscap\Writer\Factory\FullPhpWriterFactory;
use BrowscapPHP\Helper\LoggerHelper;
use Noodlehaus\Config;

$bench = new Ubench;
$bench->start();

echo ' creating browscap.ini', PHP_EOL;

$buildFolder = 'data/browser/';

$config   = new Config(['data/configs/config.json']);
$cacheDir = $config['modules']['browscap3']['cache-dir'];

$loggerHelper = new LoggerHelper();
$logger       = $loggerHelper->create(false);

$buildGenerator = new BuildGenerator(
    'vendor/browscap/browscap/resources/',
    $buildFolder
);

$writerCollectionFactory = new FullPhpWriterFactory();
$writerCollection        = $writerCollectionFactory->createCollection($logger, $buildFolder);

$buildGenerator
    ->setLogger($logger)
    ->setCollectionCreator(new CollectionCreator())
    ->setWriterCollection($writerCollection)
;

$version = (int) file_get_contents('vendor/browscap/browscap/BUILD_NUMBER');

$buildGenerator->run($version, false);

$bench->end();
echo ' ', $bench->getTime(true), ' seconds ', PHP_EOL;
echo ' ', number_format($bench->getMemoryPeak(true)), ' bytes', PHP_EOL;
