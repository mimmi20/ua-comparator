<?php

declare(strict_types = 1);

use Composer\InstalledVersions;
use Platine\UserAgent\UserAgent;

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
$bc = new UserAgent();
$r  = $bc->parse('Test String');
$r->device();
$r->browser();
$r->os();
$r->engine();
$r->cpu();
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
    $start = microtime(true);

    try {
        $r      = $bc->parse($agentString);
        $device = $r->device();
        $client = $r->browser();
        $os     = $r->os();
        $engine = $r->engine();
        $cpu    = $r->cpu();

        $parseTime = microtime(true) - $start;

        $output['result']['parsed'] = [
            'device' => [
                'architecture' => null,
                'deviceName' => $device->getModel(),
                'marketingName' => null,
                'manufacturer' => null,
                'brand' => $device->getVendor(),
                'dualOrientation' => null,
                'simCount' => null,
                'display' => [
                    'width' => null,
                    'height' => null,
                    'touch' => null,
                    'type' => null,
                    'size' => null,
                ],
                'type' => $device->getType(),
                'ismobile' => null,
                'istv' => null,
                'bits' => null,
            ],
            'client' => [
                'name' => $client->getName(),
                'modus' => null,
                'version' => $client->getVersion(),
                'manufacturer' => null,
                'bits' => null,
                'isbot' => null,
                'type' => null,
            ],
            'platform' => [
                'name' => $os->getName(),
                'marketingName' => null,
                'version' => $os->getVersion(),
                'manufacturer' => null,
                'bits' => null,
            ],
            'engine' => [
                'name' => $engine->getName(),
                'version' => $engine->getVersion(),
                'manufacturer' => null,
            ],
            'raw' => [
                'device' => (string) $device,
                'client' => (string) $client,
                'os' => (string) $os,
                'engine' => (string) $engine,
                'cpu' => (string) $cpu,
            ],
        ];
    } catch (Throwable $e) {
        trigger_error((string) $e, E_USER_WARNING);

        $parseTime = microtime(true) - $start;
    }

    $output['parse_time'] = $parseTime;
}

$output['memory_used'] = memory_get_peak_usage();

echo json_encode(
    $output,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
);
