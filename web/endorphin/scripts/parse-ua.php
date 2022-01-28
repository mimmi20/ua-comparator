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

use EndorphinStudio\Detector\Detector;

$detector = new Detector();

$detector->analyse('Test String');
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
    'version'     => \Composer\InstalledVersions::getPrettyVersion('endorphin-studio/browser-detector'),
];

if ($hasUa) {
    $start = microtime(true);
    $r     = $detector->analyse($agentString);
    $end   = microtime(true) - $start;

    $r = json_decode(json_encode($r));

    $output['result']['parsed'] = [
        'device' => [
            'deviceName'     => isset($r->device->model) ? $r->device->model : null,
            'marketingName' => null,
            'manufacturer' => null,
            'brand'    => isset($r->device->name) ? $r->device->name : null,
            'display' => [
                'width' => null,
                'height' => null,
                'touch' => $r->isTouch,
                'type' => null,
                'size' => null,
            ],
            'dualOrientation' => null,
            'type'     => isset($r->device->type) ? $r->device->type : null,
            'simCount' => null,
            'ismobile' => $r->isMobile,
        ],
        'client' => [
            'name'    => $r->isRobot ? (isset($r->robot) ? $r->robot->name : null) : ((isset($r->browser->name) && $r->browser->name !== 'not available') ? $r->browser->name : null),
            'modus' => null,
            'version' => (isset($r->browser->version) && $r->browser->version !== 'not available') ? $r->browser->version : null,
            'manufacturer' => null,
            'bits' => null,
            'type' => null,
            'isbot'   => $r->isRobot,
        ],
        'platform' => [
            'name'    => (isset($r->os->name) && $r->os->name !== 'not available') ? $r->os->name : null,
            'marketingName' => null,
            'version' => (isset($r->os->version) && $r->os->version !== 'not available') ? $r->os->version : null,
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
