<?php

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
require_once __DIR__ . '/../vendor/autoload.php';

use Composer\InstalledVersions;
use Jenssegers\Agent\Agent;

$agent = new Agent();
$agent->setUserAgent('Test String');
$agent->isDesktop();
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
    'version'     => InstalledVersions::getPrettyVersion('jenssegers/agent'),
];

if ($hasUa) {
    $start = microtime(true);
    $agent->setUserAgent($agentString);
    $device          = $agent->device();
    $platform        = $agent->platform();
    $browser         = $agent->browser();
    $isMobile        = $agent->isMobile();
    $browserVersion  = $agent->version($browser);
    $platformVersion = $agent->version($platform);
    $type            = null;
    $isBot           = false;

    if ($agent->isDesktop()) {
        $type = 'desktop';
    } elseif ($agent->isPhone()) {
        $type = 'mobile phone';
    } elseif ($agent->isTablet()) {
        $type = 'tablet';
    } elseif ($agent->isBot()) {
        $type           = null;
        $isBot          = true;
        $browser        = $agent->robot();
        $browserVersion = null;
    }

    $end = microtime(true) - $start;

    $output['result']['parsed'] = [
        'device' => [
            'architecture' => null,
            'deviceName'     => isset($device) && $device !== false ? $device : null,
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
            'type'     => $type,
            'ismobile' => $isMobile,
            'istv' => null,
            'bits' => null,
        ],
        'client' => [
            'name'    => isset($browser) && $browser !== false ? $browser : null,
            'modus' => null,
            'version' => isset($browserVersion) && $browserVersion !== false ? $browserVersion : null,
            'manufacturer' => null,
            'bits' => null,
            'isbot'   => $isBot,
            'type'    => $isBot ? 'crawler' : null,
        ],
        'platform' => [
            'name'    => isset($platform) && $platform !== false ? $platform : null,
            'marketingName' => null,
            'version' => isset($platformVersion) && $platformVersion !== false ? $platformVersion : null,
            'manufacturer' => null,
            'bits' => null,
        ],
        'engine' => [
            'name'    => null,
            'version' => null,
            'manufacturer' => null,
        ],
        'raw' => [],
    ];

    $output['parse_time'] = $end;
}

$output['memory_used'] = memory_get_peak_usage();

echo json_encode(
    $output,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
);
