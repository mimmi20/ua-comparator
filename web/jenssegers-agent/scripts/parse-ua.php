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
require_once __DIR__ . '/../vendor/autoload.php';

use Jenssegers\Agent\Agent;

$agent = new Agent();
$agent->setUserAgent('Test String');
$agent->isDesktop();
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
    'version'     => \Composer\InstalledVersions::getPrettyVersion('jenssegers/agent'),
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
            'deviceName'     => (isset($device) && false !== $device) ? $device : null,
            'marketingName' => null,
            'manufacturer' => null,
            'brand'    => null,
            'display' => [
                'width' => null,
                'height' => null,
                'touch' => null,
                'type' => null,
                'size' => null,
            ],
            'dualOrientation' => null,
            'type'     => $type,
            'simCount' => null,
            'ismobile' => $isMobile,
        ],
        'client' => [
            'name'    => (isset($browser) && false !== $browser) ? $browser : null,
            'modus' => null,
            'version' => (isset($browserVersion) && false !== $browserVersion) ? $browserVersion : null,
            'manufacturer' => null,
            'bits' => null,
            'type'    => $isBot ? 'crawler' : null,
            'isbot'   => $isBot,
        ],
        'platform' => [
            'name'    => (isset($platform) && false !== $platform) ? $platform : null,
            'marketingName' => null,
            'version' => (isset($platformVersion) && false !== $platformVersion) ? $platformVersion : null,
            'manufacturer' => null,
            'bits' => null,
        ],
        'engine' => [
            'name'    => null,
            'version' => null,
            'manufacturer' => null,
        ],
        'raw' => $browser,
    ];

    $output['parse_time'] = $end;
}

$output['memory_used'] = memory_get_peak_usage();

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
