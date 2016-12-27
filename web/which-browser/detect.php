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

$start       = microtime(true);
$parser      = new Parser(['User-Agent' => $_GET['useragent']]);
$resultArray = [
    'browser' => [
        'using'   => (isset($parser->browser->using) ? $parser->browser->using : null),
        'family'  => null,
        'channel' => (isset($parser->browser->channel) ? $parser->browser->channel : null),
        'stock'   => $parser->browser->stock,
        'hidden'  => $parser->browser->hidden,
        'mode'    => $parser->browser->mode,
        'type'    => $parser->browser->type,
        'name'    => (isset($parser->browser->name) ? $parser->browser->name : null),
        'alias'   => (isset($parser->browser->alias) ? $parser->browser->alias : null),
        'version' => (isset($parser->browser->version) ? $parser->browser->version : null),
    ],
    'engine' => [
        'name'    => (isset($parser->engine->name) ? $parser->engine->name : null),
        'alias'   => (isset($parser->engine->alias) ? $parser->engine->alias : null),
        'version' => (isset($parser->engine->version) ? $parser->engine->version : null),
    ],
    'os' => [
        'family'  => (isset($parser->os->family) ? $parser->os->family : null),
        'name'    => (isset($parser->os->name) ? $parser->os->name : null),
        'alias'   => (isset($parser->os->alias) ? $parser->os->alias : null),
        'version' => (isset($parser->os->version) ? $parser->os->version : null),
    ],
    'device' => [
        'manufacturer' => (isset($parser->device->manufacturer) ? $parser->device->manufacturer : null),
        'model'        => (isset($parser->device->model) ? $parser->device->model : null),
        'series'       => (isset($parser->device->series) ? $parser->device->series : null),
        'carrier'      => (isset($parser->device->carrier) ? $parser->device->carrier : null),
        'identifier'   => (isset($parser->device->identifier) ? $parser->device->identifier : null),
        'flag'         => (isset($parser->device->flag) ? $parser->device->flag : null),
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
