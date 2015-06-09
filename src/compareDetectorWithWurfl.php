<?php
/*
 * Information about the used detectors:
 *
 * Browscap:      http://tempdownloads.browserscap.com/
 * Wurfl:         http://wurfl.sourceforge.net/
 * UA-Parser:     https://github.com/tobie/ua-parser
 * UAS-Parser:    http://user-agent-string.info/parse
 * Mobile-Detect: https://github.com/serbanghita/Mobile-Detect
 */

use UaComparator\Helper\Check;
use UaComparator\Helper\LineHandler;
use UaComparator\Helper\LoggerFactory;
use UaComparator\Helper\MessageFormatter;
use UaComparator\Helper\TimeFormatter;
use UaComparator\Module\BrowserDetectorModule;
use UaComparator\Module\ModuleCollection;
use UaComparator\Module\Wurfl;
use UaComparator\Module\WurflOld;
use UaComparator\Source\DirectorySource;
use WurflCache\Adapter\File;

echo 'initializing App ...';

ini_set('memory_limit', '2048M');
ini_set('max_execution_time', 0);
ini_set('max_input_time', 0);
ini_set('display_errors', 1);
ini_set('error_log', './error.log');
error_reporting(E_ALL | E_DEPRECATED);

/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */
chdir(dirname(__DIR__));

require 'vendor/autoload.php';


define('ROW_LENGTH', 397);
define('COL_LENGTH', 50);
define('FIRST_COL_LENGTH', 20);

define('START_TIME', microtime(true));

define('COLOR_END', "\x1b[0m");
define('COLOR_START_RED', "\x1b[37;41m");
define('COLOR_START_YELLOW', "\x1b[30;43m");
define('COLOR_START_GREEN', "\x1b[30;42m");

/*******************************************************************************
 * time zone
 */
date_default_timezone_set('Europe/Berlin');
setlocale(LC_CTYPE, 'de_DE@euro', 'de_DE', 'de', 'ge');

$targets = array();

echo ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

/*******************************************************************************
 * Logger
 */
echo 'initializing Logger ...';

/** @var \Monolog\Logger $logger */
$logger = LoggerFactory::create();

echo ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

$collection = new ModuleCollection();

/*******************************************************************************
 * BrowserDetectorModule
 */
echo 'initializing BrowserDetectorModule (with the internal detecting engine) ...';

$adapter        = new File(array('dir' => 'data/cache/browser/'));
$detectorModule = new BrowserDetectorModule($logger, $adapter);
$detectorModule
    ->setId(0)
    ->setName('BrowserDetector')
;

$collection->addModule($detectorModule);

echo ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

/*******************************************************************************
 * WURFL - PHP 5.3 port
 */

echo 'initializing Wurfl API (PHP-API 5.3 port) ...';

ini_set('max_input_time', '6000');
$adapter     = new File(array('dir' => 'data/cache/wurfl/'));
$wurflModule = new Wurfl($logger, $adapter, 'data/wurfl-config.xml');
$wurflModule
    ->setId(11)
    ->setName('WURFL API (PHP-API 5.3)')
;

$collection->addModule($wurflModule);

echo ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

/*******************************************************************************
 * WURFL - PHP 5.2 original
 */

echo 'initializing Wurfl API (PHP-API 5.2 original) ...';

$adapter        = new File(array('dir' => 'data/cache/wurfl_old/'));
$oldWurflModule = new WurflOld($logger, $adapter, 'data/wurfl-config.xml');
$oldWurflModule
    ->setId(7)
    ->setName('WURFL API (PHP-API 5.2 original)')
;

$collection->addModule($oldWurflModule);

echo ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

/*******************************************************************************
 * init
 */

echo 'initializing all Modules ...';

$collection->init();

echo ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

/*******************************************************************************
 * Loop
 */

$i       = 1;
$count   = 0;
$aLength = COL_LENGTH + 1 + COL_LENGTH + 1 + (($collection->count() - 1) * (COL_LENGTH + 1));

$messageFormatter = new MessageFormatter();
$messageFormatter
    ->setCollection($collection)
    ->setColumnsLength(COL_LENGTH)
;

echo str_repeat('+', FIRST_COL_LENGTH + $aLength + $collection->count() - 1 + 2) . "\n";

$oldMemery = 0;
$okfound   = 0;
$nokfound  = 0;
$sosofound = 0;
$weights   = array(
    'manufacturers' => array(),
    'devices'       => array(),
    'browsers'      => array(),
    'engine'        => array(),
    'os'            => array()
);

echo "\n";

$uaSourceDirectory = 'data/useragents';
$source            = new DirectorySource();
$lineHandler       = new LineHandler();

$checkHelper = new Check();
$checks      = $checkHelper->getChecks(Check::MINIMUM, $collection);

/*******************************************************************************
 * Loop
 */
foreach ($source->getUserAgents($uaSourceDirectory, $logger) as $line) {
    try {
        $lineHandler->handleLine($line, $collection, $messageFormatter, $i, $checks);
    } catch (\Exception $e) {
        if (3 === $e->getCode()) {
            $okfound++;
        } elseif (2 === $e->getCode()) {
            $sosofound++;
        } else {
            $nokfound++;
        }
    }

    $content = str_replace(
        array(
            '#count#',
            '#plus#',
            '#minus#',
            '#soso#',
            '#percent1#',
            '#percent2#',
            '#percent3#',
        ),
        array(
            str_pad(number_format(0, 0, ',', '.'), FIRST_COL_LENGTH - 7, ' ', STR_PAD_LEFT),
            str_pad($okfound, FIRST_COL_LENGTH - 11, ' ', STR_PAD_LEFT) ,
            str_pad($nokfound, FIRST_COL_LENGTH - 11, ' ', STR_PAD_LEFT),
            str_pad($sosofound, FIRST_COL_LENGTH - 11, ' ', STR_PAD_LEFT),
            str_pad(number_format((100 * $okfound / $i), 9, ',', '.'), FIRST_COL_LENGTH - 4, ' ', STR_PAD_LEFT),
            str_pad(number_format((100 * $nokfound / $i), 9, ',', '.'), FIRST_COL_LENGTH - 4, ' ', STR_PAD_LEFT),
            str_pad(number_format((100 * $sosofound / $i), 9, ',', '.'), FIRST_COL_LENGTH - 4, ' ', STR_PAD_LEFT),
        ),
        $e->getMessage()
    );

    echo $content;

    $i++;
}

echo "\n" . str_repeat('-', FIRST_COL_LENGTH) . '+' . str_repeat('-', $collection->count() - 1) . '+' . str_repeat('-', $aLength) . "\n";

$content = '#plus# + detected|' . "\n"
    . '#percent1# % +|' . "\n"
    . '#minus# - detected|' . "\n"
    . '#percent2# % -|' . "\n"
    . '#soso# : detected|' . "\n"
    . '#percent3# % :|' . "\n";

--$i;

if ($i < 1) {
    $i = 1;
}

$content = str_replace(
    array(
        '#plus#',
        '#minus#',
        '#soso#',
        '#percent1#',
        '#percent2#',
        '#percent3#',
    ),
    array(
        substr(str_repeat(' ', FIRST_COL_LENGTH) . $okfound, -(FIRST_COL_LENGTH - 11)),
        substr(str_repeat(' ', FIRST_COL_LENGTH) . $nokfound, -(FIRST_COL_LENGTH - 11)),
        substr(str_repeat(' ', FIRST_COL_LENGTH) . $sosofound, -(FIRST_COL_LENGTH - 11)),
        substr(str_repeat(' ', FIRST_COL_LENGTH) . number_format((100 * $okfound / $i), 9, ',', '.'), -(FIRST_COL_LENGTH - 4)),
        substr(str_repeat(' ', FIRST_COL_LENGTH) . number_format((100 * $nokfound / $i), 9, ',', '.'), -(FIRST_COL_LENGTH - 4)),
        substr(str_repeat(' ', FIRST_COL_LENGTH) . number_format((100 * $sosofound / $i), 9, ',', '.'), -(FIRST_COL_LENGTH - 4)),
    ),
    $content
);


echo substr(str_repeat(' ', FIRST_COL_LENGTH) . $i . '/' . $count, -1 * FIRST_COL_LENGTH) . '|' . "\n" . $content;

$len = FIRST_COL_LENGTH + COL_LENGTH;

asort($weights['manufacturers'], SORT_NUMERIC);
asort($weights['devices'], SORT_NUMERIC);
asort($weights['browsers'], SORT_NUMERIC);
asort($weights['engine'], SORT_NUMERIC);
asort($weights['os'], SORT_NUMERIC);

echo str_repeat('-', FIRST_COL_LENGTH) . '+' . str_repeat('-', $collection->count() - 1 + $aLength + 1) . "\n";
echo 'Weight of Device Manufacturers' . "\n";

$weights['manufacturers'] = array_reverse($weights['manufacturers']);

foreach ($weights['manufacturers'] as $manufacturer => $weight) {
    echo substr(str_repeat(' ', $len) . $manufacturer, -1 * $len) . '|' . substr(str_repeat(' ', FIRST_COL_LENGTH) . number_format($weight, 0, ',', '.'), -1 * FIRST_COL_LENGTH) . "\n";
}

echo str_repeat('-', FIRST_COL_LENGTH) . '+' . str_repeat('-', $collection->count() - 1 + $aLength + 1) . "\n";
echo 'Weight of Devices' . "\n";

$weights['devices'] = array_reverse($weights['devices']);

foreach ($weights['devices'] as $device => $weight) {
    echo substr(str_repeat(' ', $len) . $device, -1 * $len) . '|' . substr(str_repeat(' ', FIRST_COL_LENGTH) . number_format($weight, 0, ',', '.'), -1 * FIRST_COL_LENGTH) . "\n";
}

echo str_repeat('-', FIRST_COL_LENGTH) . '+' . str_repeat('-', $collection->count() - 1 + $aLength + 1) . "\n";
echo 'Weight of Browsers' . "\n";

$weights['browsers'] = array_reverse($weights['browsers']);

foreach ($weights['browsers'] as $browser => $weight) {
    echo substr(str_repeat(' ', $len) . $browser, -1 * $len) . '|' . substr(str_repeat(' ', FIRST_COL_LENGTH) . number_format($weight, 0, ',', '.'), -1 * FIRST_COL_LENGTH) . "\n";
}

echo str_repeat('-', FIRST_COL_LENGTH) . '+' . str_repeat('-', $collection->count() - 1 + $aLength + 1) . "\n";
echo 'Weight of Rendering Engines' . "\n";

$weights['engine'] = array_reverse($weights['engine']);

foreach ($weights['engine'] as $engine => $weight) {
    echo substr(str_repeat(' ', $len) . $engine, -1 * $len) . '|' . substr(str_repeat(' ', FIRST_COL_LENGTH) . number_format($weight, 0, ',', '.'), -1 * FIRST_COL_LENGTH) . "\n";
}

echo str_repeat('-', FIRST_COL_LENGTH) . '+' . str_repeat('-', $collection->count() - 1 + $aLength + 1) . "\n";
echo 'Weight of Platforms' . "\n";

$weights['os'] = array_reverse($weights['os']);

foreach ($weights['os'] as $os => $weight) {
    echo substr(str_repeat(' ', $len) . $os, -1 * $len) . '|' . substr(str_repeat(' ', FIRST_COL_LENGTH) . number_format($weight, 0, ',', '.'), -1 * FIRST_COL_LENGTH) . "\n";
}

echo str_repeat('-', FIRST_COL_LENGTH) . '+' . str_repeat('-', $collection->count() - 1 + $aLength + 1) . "\n";

// End
echo str_repeat('+', FIRST_COL_LENGTH + $aLength + $collection->count() - 1 + 2) . "\n";
