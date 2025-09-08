<?php

declare(strict_types = 1);

use Composer\InstalledVersions;
use Fyre\Http\UserAgent;

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
$userAgent = new UserAgent('Test String');
$userAgent->getBrowser();
$userAgent->getVersion();
$userAgent->getPlatform();
$userAgent->isMobile();
$userAgent->isRobot();
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
    'version'     => InstalledVersions::getPrettyVersion('cbschuld/browser.php'),
];

if ($hasUa) {
    $start     = microtime(true);
    $userAgent = new UserAgent($agentString);
    $browser   = $userAgent->getBrowser();
    $version   = $userAgent->getVersion();
    $os        = $userAgent->getPlatform();
    $isMobile  = $userAgent->isMobile();
    $isBot     = $userAgent->isRobot();
    $parseTime = microtime(true) - $start;

    $output['result']['parsed'] = [
        'device' => [
            'architecture' => null,
            'deviceName' => null,
            'marketingName' => null,
            'manufacturer' => null,
            'brand' => null,
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
            'ismobile' => $isMobile,
            'istv' => null,
            'bits' => null,
        ],
        'client' => [
            'name' => $browser,
            'modus' => null,
            'version' => $version,
            'manufacturer' => null,
            'bits' => null,
            'isbot' => $isBot,
            'type' => null,
        ],
        'platform' => [
            'name' => $os,
            'marketingName' => null,
            'version' => null,
            'manufacturer' => null,
            'bits' => null,
        ],
        'engine' => [
            'name' => null,
            'version' => null,
            'manufacturer' => null,
        ],
        'raw' => null,
    ];

    $output['parse_time'] = $parseTime;
}

$output['memory_used'] = memory_get_peak_usage();

echo json_encode(
    $output,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
);
