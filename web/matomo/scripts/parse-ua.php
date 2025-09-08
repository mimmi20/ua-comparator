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

require_once __DIR__ . '/../vendor/autoload.php';

use Composer\InstalledVersions;
use DeviceDetector\Cache\PSR16Bridge;
use DeviceDetector\DeviceDetector;
use MatthiasMullie\Scrapbook\Adapters\MemoryStore;
use MatthiasMullie\Scrapbook\Psr16\SimpleCache;

$cache = new SimpleCache(
    new MemoryStore(),
);

$start = microtime(true);
$dd    = new DeviceDetector('Test String');
$dd->setCache(new PSR16Bridge($cache));
$dd->parse();
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
    'version'     => InstalledVersions::getPrettyVersion('matomo/device-detector'),
];

if ($hasUa) {
    $dd->setUserAgent($agentString);

    $start = microtime(true);
    $dd->parse();

    $clientInfo = $dd->getClient();
    $osInfo     = $dd->getOs();
    $model      = $dd->getModel();
    $brand      = $dd->getBrandName();
    $device     = $dd->getDeviceName();
    $isMobile   = $dd->isMobile();
    $isBot      = $dd->isBot();
    $botInfo    = $dd->getBot();

    $end = microtime(true) - $start;

    $output['result']['parsed'] = [
        'device' => [
            'architecture' => null,
            'deviceName'     => $model ?? null,
            'marketingName' => null,
            'manufacturer' => null,
            'brand'    => $brand ?? null,
            'dualOrientation' => null,
            'simCount' => null,
            'display' => [
                'width' => null,
                'height' => null,
                'touch' => null,
                'type' => null,
                'size' => null,
            ],
            'type'     => $device ?? null,
            'ismobile' => $isMobile,
            'istv' => null,
            'bits' => null,
        ],
        'client' => [
            'name'    => $isBot ? ($botInfo['name'] ?? null) : ($clientInfo['name'] ?? null),
            'modus' => null,
            'version' => $isBot ? null : ($clientInfo['version'] ?? null),
            'manufacturer' => null,
            'bits' => null,
            'isbot' => $isBot,
            'type' => $isBot ? ($botInfo['category'] ?? null) : ($clientInfo['type'] ?? null),
        ],
        'platform' => [
            'name'    => $osInfo['name'] ?? null,
            'marketingName' => null,
            'version' => $osInfo['version'] ?? null,
            'manufacturer' => null,
            'bits' => null,
        ],
        'engine' => [
            'name'    => $isBot ? null : ($clientInfo['engine'] ?? null),
            'version' => $isBot ? null : ($clientInfo['engine_version'] ?? null),
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
