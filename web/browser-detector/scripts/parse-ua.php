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

require __DIR__ . '/../vendor/autoload.php';

$cache   = new \MatthiasMullie\Scrapbook\Psr16\SimpleCache(
    new \MatthiasMullie\Scrapbook\Adapters\MemoryStore()
);

$start = microtime(true);
$logger    = new \Psr\Log\NullLogger();
$factory   = new \BrowserDetector\DetectorFactory($cache, $logger);
$detector  = $factory();
$detector('Test String');
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
    'version'     => \Composer\InstalledVersions::getPrettyVersion('mimmi20/browser-detector'),
];

if ($hasUa) {
    $start = microtime(true);
    $r     = $detector($agentString);
    $end   = microtime(true) - $start;

    $output['result']['parsed'] = [
        'device' => [
            'deviceName'     => $r->getDevice()->getDeviceName(),
            'marketingName' => $r->getDevice()->getMarketingName(),
            'manufacturer' => $r->getDevice()->getManufacturer()->getName(),
            'brand'    => $r->getDevice()->getBrand()->getBrandName(),
            'display' => [
                'width' => $r->getDevice()->getDisplay()->getWidth(),
                'height' => $r->getDevice()->getDisplay()->getHeight(),
                'touch' => $r->getDevice()->getDisplay()->hasTouch(),
                'type' => null,
                'size' => $r->getDevice()->getDisplay()->getSize(),
            ],
            'dualOrientation' => null,
            'type'     => $r->getDevice()->getType()->getName(),
            'simCount' => null,
            'ismobile' => $r->getDevice()->getType()->isMobile(),
        ],
        'client' => [
            'name'    => $r->getBrowser()->getName(),
            'modus' => $r->getBrowser()->getModus(),
            'version' => $r->getBrowser()->getVersion()->getVersion(),
            'manufacturer' => $r->getBrowser()->getManufacturer()->getName(),
            'bits' => $r->getBrowser()->getBits(),
            'type'    => $r->getBrowser()->getType()->getType(),
            'isbot'   => $r->getBrowser()->getType()->isBot(),
        ],
        'platform' => [
            'name'    => $r->getOs()->getName(),
            'marketingName' => $r->getOs()->getMarketingName(),
            'version' => $r->getOs()->getVersion()->getVersion(),
            'manufacturer' => $r->getOs()->getManufacturer()->getName(),
            'bits' => $r->getOs()->getBits(),
        ],
        'engine' => [
            'name'    => $r->getEngine()->getName(),
            'version' => $r->getEngine()->getVersion()->getVersion(),
            'manufacturer' => $r->getEngine()->getManufacturer()->getName(),
        ],
        'raw' => $r->toArray(),
    ];

    $output['parse_time'] = $end;
}

$output['memory_used'] = memory_get_peak_usage();

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
