<?php

declare(strict_types = 1);

use Composer\InstalledVersions;
use Wolfcast\BrowserDetection;

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
new BrowserDetection('Test String');
$initTime = microtime(true) - $start;

$output = [
    'hasUa' => $hasUa,
    'headers' => ['user-agent' => $agentString],
    'result'      => [
        'parsed' => null,
        'err'    => null,
    ],
    'parse_time'  => 0,
    'init_time'   => $initTime,
    'memory_used' => 0,
    'version'     => InstalledVersions::getPrettyVersion('wolfcast/browser-detection'),
];

if ($hasUa) {
    $start  = microtime(true);
    $result = new BrowserDetection($agentString);
    $end    = microtime(true) - $start;

    $output['result']['parsed'] = [
        'device' => [
            'architecture' => null,
            'deviceName'     => null,
            'marketingName' => null,
            'manufacturer' => null,
            'brand'    => null,
            'dualOrientation' => null,
            'simCount' => null,
            'display' => [
                'width' => null,
                'height' => null,
                'touch' => null,
                'type' => null,
                'size' => null,
            ],
            'type'     => null,
            'ismobile' => $result->isMobile(),
            'istv' => null,
            'bits' => null,
        ],
        'client' => [
            'name'    => $result->getName() !== 'unknown' ? $result->getName() : null,
            'modus' => null,
            'version' => $result->getVersion() !== 'unknown' ? $result->getVersion() : null,
            'manufacturer' => null,
            'bits' => null,
            'isbot'    => null,
            'type' => null,
        ],
        'platform' => [
            'name'    => $result->getPlatform() !== 'unknown' ? $result->getPlatform() : null,
            'marketingName' => null,
            'version' => $result->getPlatformVersion(true) !== 'unknown' ? $result->getPlatformVersion(
                true,
            ) : null,
            'manufacturer' => null,
            'bits' => null,
        ],
        'engine' => [
            'name'    => null,
            'version' => null,
            'manufacturer' => null,
        ],
        'raw' => null,
    ];

    $output['parse_time'] = $end;
}

$output['memory_used'] = memory_get_peak_usage();

echo json_encode(
    $output,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
);
