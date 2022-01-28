#!/usr/bin/env php
<?php

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
require __DIR__ . '/../vendor/autoload.php';
$parser = UAParser\Parser::create();
$parser->parse('Test String');
$initTime = microtime(true) - $start;

$regexVersion = file_get_contents(__DIR__ . '/../version.txt');

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
    'version'     => \Composer\InstalledVersions::getPrettyVersion('ua-parser/uap-php') . '-' . $regexVersion,
];

if ($hasUa) {
    $start = microtime(true);
    $r     = $parser->parse($agentString);
    $browserVersion  = $r->ua->toVersion();
    $platformVersion = $r->ua->toVersion();

    $end   = microtime(true) - $start;

    $output['result']['parsed'] = [
        'device' => [
            'deviceName'     => $r->device->model === null ? null : $r->device->model,
            'marketingName' => null,
            'manufacturer' => null,
            'brand'    => $r->device->brand === null ? null : $r->device->brand,
            'display' => [
                'width' => null,
                'height' => null,
                'touch' => null,
                'type' => null,
                'size' => null,
            ],
            'dualOrientation' => null,
            'type'     => null,
            'simCount' => null,
            'ismobile' => null,
        ],
        'client' => [
            'name'    => $r->ua->family === 'Other' ? null : $r->ua->family,
            'modus' => null,
            'version' => $browserVersion !== '' ? $browserVersion : null,
            'manufacturer' => null,
            'bits' => null,
            'type' => null,
            'isbot'    => null,
        ],
        'platform' => [
            'name'    => $r->os->family === 'Other' ? null : $r->os->family,
            'marketingName' => null,
            'version' => $platformVersion !== '' ? $platformVersion : null,
            'manufacturer' => null,
            'bits' => null,
        ],
        'engine' => [
            'name'    => null,
            'version' => null,
            'manufacturer' => null,
        ],
        'raw' => $r,
    ];

    $output['parse_time'] = $end;
}

$output['memory_used'] = memory_get_peak_usage();

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
