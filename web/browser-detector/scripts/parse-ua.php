<?php

declare(strict_types = 1);

use BrowserDetector\DetectorFactory;
use Composer\InstalledVersions;
use MatthiasMullie\Scrapbook\Adapters\MemoryStore;
use MatthiasMullie\Scrapbook\Psr16\SimpleCache;
use Psr\Log\NullLogger;

ini_set('memory_limit', '-1');
ini_set('max_execution_time', '-1');

$uaPos       = array_search('--ua', $argv, true);
$hasUa       = false;
$agentString = '';

if ($uaPos !== false) {
    $hasUa = true;

    $agentString = $argv[2];
}

require __DIR__ . '/../vendor/autoload.php';

$cache = new SimpleCache(
    new MemoryStore(),
);

$start    = microtime(true);
$logger   = new NullLogger();
$factory  = new DetectorFactory($cache, $logger);
$detector = $factory();
$detector->getBrowser('Test String');
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
    'version'     => InstalledVersions::getPrettyVersion('mimmi20/browser-detector'),
];

if ($hasUa) {
    $start = microtime(true);
    $r     = $detector->getBrowser($agentString);
    $end   = microtime(true) - $start;

    $output['result']['parsed'] = [
        'device' => $r['device'],
        'client' => $r['client'],
        'platform' => $r['os'],
        'engine' => $r['engine'],
        'raw' => $r,
    ];

    $output['parse_time'] = $end;
}

$output['memory_used'] = memory_get_peak_usage();

echo json_encode(
    $output,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
);
