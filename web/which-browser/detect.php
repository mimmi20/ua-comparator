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

use WhichBrowser\Parser;

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

header('Content-Type: application/json', true);

$start = microtime(true);
$parser = new Parser(['User-Agent' => $_POST['useragent']]);
$resultArray = [
    'browser'    => [
        'using'   => $parser->browser->using,
        'family'  => null,
        'channel' => $parser->browser->channel,
        'stock'   => $parser->browser->stock,
        'hidden'  => $parser->browser->hidden,
        'mode'    => $parser->browser->mode,
        'type'    => $parser->browser->type,
        'name'    => $parser->browser->name,
        'alias'   => $parser->browser->alias,
        'version' => $parser->browser->version,
    ],
    'engine'     => [
        'name'    => $parser->engine->name,
        'alias'   => $parser->engine->alias,
        'version' => $parser->engine->version,
    ],
    'os'         => [
        'family'  => $parser->os->family,
        'name'    => $parser->os->name,
        'alias'   => $parser->os->alias,
        'version' => $parser->os->version,
    ],
    'device'     => [
        'manufacturer' => $parser->device->manufacturer,
        'model'        => $parser->device->model,
        'series'       => $parser->device->series,
        'carrier'      => $parser->device->carrier,
        'identifier'   => $parser->device->identifier,
        'flag'         => $parser->device->flag,
        'type'         => $parser->device->type,
        'subtype'      => $parser->device->subtype,
        'identified'   => $parser->device->identified,
        'generic'      => $parser->device->generic,
    ],
    'camouflage' => $parser->camouflage,
];
$duration = microtime(true) - $start;

echo json_encode(
    [
        'result'   => $resultArray,
        'duration' => $duration,
        'memory'   => memory_get_usage(true),
    ]
);
