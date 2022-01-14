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
require __DIR__ . '/../vendor/autoload.php';
$parser = new \Woothee\Classifier();
$parser->parse('Test String');
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
    'version'     => \Composer\InstalledVersions::getPrettyVersion('woothee/woothee'),
];

if ($hasUa) {
    $start = microtime(true);
    $r     = $parser->parse($agentString);
    $end   = microtime(true) - $start;

    $output['result']['parsed'] = [
        'device' => [
            'deviceName'     => null,
            'marketingName' => null,
            'manufacturer' => null,
            'brand'    => (isset($r['vendor']) && $r['vendor'] !== 'UNKNOWN') ? $r['vendor'] : null,
            'display' => [
                'width' => null,
                'height' => null,
                'touch' => null,
                'type' => null,
                'size' => null,
            ],
            'dualOrientation' => null,
            'type'     => (isset($r['category']) && $r['category'] !== 'UNKNOWN') ? $r['category'] : null,
            'simCount' => null,
            'ismobile' => null,
        ],
        'client' => [
            'name'    => (isset($r['name']) && $r['name'] !== 'UNKNOWN') ? $r['name'] : null,
            'modus' => null,
            'version' => (isset($r['version']) && $r['version'] !== 'UNKNOWN') ? $r['version'] : null,
            'manufacturer' => null,
            'bits' => null,
            'type' => null,
            'isbot'    => null,
        ],
        'platform' => [
            'name'    => (isset($r['os']) && $r['os'] !== 'UNKNOWN') ? $r['os'] : null,
            'marketingName' => null,
            'version' => (isset($r['os_version']) && $r['os_version'] !== 'UNKNOWN') ? $r['os_version'] : null,
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
