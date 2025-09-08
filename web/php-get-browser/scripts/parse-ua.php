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
get_browser('Test String');
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
    'version' => PHP_VERSION,
];

if ($hasUa) {
    $start = microtime(true);
    $r     = get_browser($agentString);
    $end   = microtime(true) - $start;

    $output['result']['parsed'] = [
        'device' => [
            'architecture' => null,
            'deviceName' => $r->device_name ?? null,
            'marketingName' => null,
            'manufacturer' => null,
            'brand' => $r->device_maker ?? null,
            'dualOrientation' => null,
            'simCount' => null,
            'display' => [
                'width' => null,
                'height' => null,
                'touch' => (isset($r->device_pointing_method) && $r->device_pointing_method === 'touchscreen'),
                'type' => null,
                'size' => null,
            ],
            'type' => $r->device_type ?? null,
            'ismobile' => $r->ismobiledevice ?? null,
            'istv' => null,
            'bits' => null,
        ],
        'client' => [
            'name' => $r->browser ?? null,
            'modus' => $r->browser_modus ?? null,
            'version' => $r->version ?? null,
            'manufacturer' => $r->browser_maker ?? null,
            'bits' => $r->browser_bits ?? null,
            'isbot' => $r->crawler ?? null,
            'type' => $r->browser_type ?? null,
        ],
        'platform' => [
            'name' => $r->platform ?? null,
            'marketingName' => null,
            'version' => $r->platform_version ?? null,
            'manufacturer' => $r->platform_maker ?? null,
            'bits' => $r->platform_bits ?? null,
        ],
        'engine' => [
            'name' => $r->renderingengine_name ?? null,
            'version' => $r->renderingengine_version ?? null,
            'manufacturer' => $r->renderingengine_maker ?? null,
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
