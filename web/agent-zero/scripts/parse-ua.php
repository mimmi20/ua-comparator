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
use hexydec\agentzero\agentzero;

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

require_once __DIR__ . '/../vendor/autoload.php';
agentzero::parse('Test String');
$initTime = microtime(true) - $start;

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
    'version' => InstalledVersions::getPrettyVersion('hexydec/agentzero'),
];

if ($hasUa) {
    $start     = microtime(true);
    $r         = agentzero::parse($agentString);
    $parseTime = microtime(true) - $start;

    if ($r !== false) {
        $output['result']['parsed'] = [
            'device' => [
                'architecture' => $r->architecture,
                'deviceName' => null,
                'marketingName' => $r->device === null ? null : mb_trim($r->device . ' ' . $r->model),
                'manufacturer' => null,
                'brand' => $r->vendor,
                'dualOrientation' => null,
                'simCount' => null,
                'display' => [
                    'width' => $r->width,
                    'height' => $r->height,
                    'touch' => null,
                    'type' => null,
                    'size' => null,
                ],
                'type' => $r->type === 'robot' ? null : $r->category,
                'ismobile' => $r->ismobiledevice ?? null,
                'istv' => null,
                'bits' => $r->bits,
            ],
            'client' => [
                'name' => $r->app ?? $r->browser,
                'modus' => null,
                'version' => $r->appversion ?? $r->browserversion,
                'manufacturer' => null,
                'bits' => null,
                'isbot' => $r->type === 'robot',
                'type' => $r->type !== 'robot' ? null : $r->category,
            ],
            'platform' => [
                'name' => $r->platform,
                'marketingName' => null,
                'version' => $r->platformversion,
                'manufacturer' => null,
                'bits' => null,
            ],
            'engine' => [
                'name' => $r->engine,
                'version' => $r->engineversion,
                'manufacturer' => null,
            ],
            'raw' => get_object_vars($r),
        ];
    }

    $output['parse_time'] = $parseTime;
}

$output['memory_used'] = memory_get_peak_usage();

echo json_encode(
    $output,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
);
