#!/usr/bin/env php
<?php

/**
 * This file is part of the mimmi20/ua-comparator package.
 *
 * Copyright (c) 2015-2025, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

use Composer\InstalledVersions;
use UAParser\Parser;

ini_set('memory_limit', '-1');
ini_set('max_execution_time', '-1');

$uaPos       = array_search('--ua', $argv, true);
$hasUa       = false;
$agentString = '';

if ($uaPos !== false) {
    $hasUa = true;

    $agentString = $argv[2];
}

$start = microtime(true);

require __DIR__ . '/../vendor/autoload.php';
$parser = Parser::create();
$parser->parse('Test String');
$initTime = microtime(true) - $start;

$regexVersion = file_get_contents(__DIR__ . '/../data/version.txt');

$output = [
    'hasUa' => $hasUa,
    'headers' => ['user-agent' => $agentString],
    'result' => [
        'parsed' => null,
        'err' => null,
    ],
    'parse_time' => 0,
    'init_time' => $initTime,
    'memory_used' => 0,
    'version' => InstalledVersions::getPrettyVersion('ua-parser/uap-php') . '-' . $regexVersion,
];

if ($hasUa) {
    $start           = microtime(true);
    $r               = $parser->parse($agentString);
    $browserVersion  = $r->ua->toVersion();
    $platformVersion = $r->ua->toVersion();

    $end = microtime(true) - $start;

    $output['result']['parsed'] = [
        'device' => [
            'architecture' => null,
            'deviceName' => $r->device->model ?? null,
            'marketingName' => null,
            'manufacturer' => null,
            'brand' => $r->device->brand ?? null,
            'dualOrientation' => null,
            'simCount' => null,
            'display' => [
                'width' => null,
                'height' => null,
                'touch' => null,
                'type' => null,
                'size' => null,
            ],
            'type' => null,
            'ismobile' => null,
            'istv' => null,
            'bits' => null,
        ],
        'client' => [
            'name' => $r->ua->family === 'Other' ? null : $r->ua->family,
            'modus' => null,
            'version' => $browserVersion !== '' ? $browserVersion : null,
            'manufacturer' => null,
            'bits' => null,
            'isbot' => null,
            'type' => null,
        ],
        'platform' => [
            'name' => $r->os->family === 'Other' ? null : $r->os->family,
            'marketingName' => null,
            'version' => $platformVersion !== '' ? $platformVersion : null,
            'manufacturer' => null,
            'bits' => null,
        ],
        'engine' => [
            'name' => null,
            'version' => null,
            'manufacturer' => null,
        ],
        'raw' => $r,
    ];

    $output['parse_time'] = $end;
}

$output['memory_used'] = memory_get_peak_usage();

echo json_encode(
    $output,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
);
