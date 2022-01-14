<?php

declare(strict_types = 1);
ini_set('memory_limit', '-1');
ini_set('max_execution_time', '-1');

$uaPos       = array_search('--ua', $argv);
$hasUa       = false;
$agentString = '';

if ($uaPos !== false) {
    $hasUa = true;

    $agentString = $argv[2];
}

$result    = null;
$parseTime = 0;

$start = microtime(true);
get_browser('Test String');
$initTime = microtime(true) - $start;

$output = [
    'hasUa' => $hasUa,
    'headers' => [
        'user-agent' => $agentString,
    ],
    'result'      => [
        'parsed' => null,
        'err'    => null,
    ],
    'parse_time'  => 0,
    'init_time'   => $initTime,
    'memory_used' => 0,
    'version'     => PHP_VERSION . '-' . file_get_contents(__DIR__ . '/../data/version.txt'),
];

if ($hasUa) {
    $start = microtime(true);
    $r     = get_browser($agentString);
    $end   = microtime(true) - $start;

    $output['result']['parsed'] = [
        'device' => [
            'deviceName'     => ($r->device_name && $r->device_name !== 'unknown') ? $r->device_name : null,
            'marketingName' => null,
            'manufacturer' => null,
            'brand'    => ($r->device_maker && $r->device_maker !== 'unknown') ? $r->device_maker : null,
            'display' => [
                'width' => null,
                'height' => null,
                'touch' => (isset($r->device_pointing_method) && $r->device_pointing_method === 'touchscreen'),
                'type' => null,
                'size' => null,
            ],
            'dualOrientation' => null,
            'type'     => ($r->device_type && $r->device_type !== 'unknown') ? $r->device_type : null,
            'simCount' => null,
            'ismobile' => property_exists($r, 'ismobiledevice') ? $r->ismobiledevice : null,
        ],
        'client' => [
            'name'    => ($r->browser && $r->browser !== 'unknown') ? $r->browser : null,
            'modus' => ($r->browser_modus && $r->browser_modus !== 'unknown') ? $r->browser_modus : null,
            'version' => ($r->version && $r->version !== 'unknown') ? $r->version : null,
            'manufacturer' => ($r->browser_maker && $r->browser_maker !== 'unknown') ? $r->browser_maker : null,
            'bits' => ($r->browser_bits && $r->browser_bits !== 'unknown') ? $r->browser_bits : null,
            'isBot'   => property_exists($r, 'crawler') ? $r->crawler : null,
            'type'    => $r->browser_type ?? null,
        ],
        'platform' => [
            'name'    => ($r->platform && $r->platform !== 'unknown') ? $r->platform : null,
            'marketingName' => null,
            'version' => ($r->platform_version && $r->platform_version !== 'unknown') ? $r->platform_version : null,
            'manufacturer' => ($r->platform_maker && $r->platform_maker !== 'unknown') ? $r->platform_maker : null,
            'bits' => ($r->platform_bits && $r->platform_bits !== 'unknown') ? $r->platform_bits : null,
        ],
        'engine' => [
            'name'    => ($r->renderingengine_name && $r->renderingengine_name !== 'unknown') ? $r->renderingengine_name : null,
            'version' => ($r->renderingengine_version && $r->renderingengine_version !== 'unknown') ? $r->renderingengine_version : null,
            'manufacturer' => ($r->renderingengine_maker && $r->renderingengine_maker !== 'unknown') ? $r->renderingengine_maker : null,
        ],
        'raw' => $r,
    ];

    $output['parse_time'] = $end;
}

$output['memory_used'] = memory_get_peak_usage();

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
