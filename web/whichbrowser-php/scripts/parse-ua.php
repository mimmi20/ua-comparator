<?php

declare(strict_types = 1);

use Composer\InstalledVersions;
use MatthiasMullie\Scrapbook\Adapters\MemoryStore;
use MatthiasMullie\Scrapbook\Psr6\Pool;
use WhichBrowser\Parser;

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

$parser = new Parser();

$cache = new Pool(
    new MemoryStore(),
);

$start = microtime(true);
$parser->analyse(['User-Agent' => 'Test String'], ['cache' => $cache]);
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
    'version'     => InstalledVersions::getPrettyVersion('whichbrowser/parser'),
];

if ($hasUa) {
    $start = microtime(true);
    $parser->analyse(['User-Agent' => $agentString], ['cache' => $cache]);
    $isMobile = $parser->isMobile();
    $end      = microtime(true) - $start;

    $output['result']['parsed'] = [
        'device' => [
            'architecture' => null,
            'deviceName'     => $parser->device->model ?? null,
            'marketingName' => null,
            'manufacturer' => null,
            'brand'    => $parser->device->manufacturer ?? null,
            'dualOrientation' => null,
            'simCount' => null,
            'display' => [
                'width' => null,
                'height' => null,
                'touch' => null,
                'type' => null,
                'size' => null,
            ],
            'type'     => $parser->device->type ?? null,
            'ismobile' => $isMobile,
            'istv' => null,
            'bits' => null,
        ],
        'client' => [
            'name'    => $parser->browser->name ?? null,
            'modus' => null,
            'version' => $parser->browser->version->value ?? null,
            'manufacturer' => null,
            'bits' => null,
            'isbot'    => null,
            'type' => null,
        ],
        'platform' => [
            'name'    => $parser->os->name ?? null,
            'marketingName' => null,
            'version' => $parser->os->version->value ?? null,
            'manufacturer' => null,
            'bits' => null,
        ],
        'engine' => [
            'name'    => $parser->engine->name ?? null,
            'version' => $parser->engine->version->value ?? null,
            'manufacturer' => null,
        ],
        'raw' => $parser->toArray(),
    ];

    $output['parse_time'] = $end;
}

$output['memory_used'] = memory_get_peak_usage();

echo json_encode(
    $output,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
);
