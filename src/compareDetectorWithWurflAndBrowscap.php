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

use Browscap\Generator\BuildFullFileOnlyGenerator;
use BrowserDetector\Detector\Version;
use UaComparator\Helper\LoggerFactory;
use UaComparator\Module\Browscap;
use UaComparator\Module\BrowserDetectorModule;
use UaComparator\Module\CrossJoin;
use UaComparator\Module\ModuleCollection;
use UaComparator\Module\UaParser;
use UaComparator\Module\UasParser;
use UaComparator\Module\Wurfl;
use UaComparator\Module\WurflOld;
use UAS\Parser;
use Wurfl\Configuration\XmlConfig;
use Wurfl\Manager;
use WurflCache\Adapter\File;
use WurflCache\Adapter\Memory;
use WurflCache\Adapter\NullStorage;
use UaComparator\Helper\MessageFormatter;
use UaComparator\Helper\TimeFormatter;
use BrowscapPHP\Helper\IniLoader;
use Monolog\Logger;

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

$logger = LoggerFactory::create();

echo ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

$collection = new ModuleCollection();

/*******************************************************************************
 * BrowserDetectorModule
 */
echo 'initializing BrowserDetectorModule (with the internal detecting engine) ...';

$detectorModule = new BrowserDetectorModule($logger, new File(array('dir' => 'data/cache/browser/')));
$detectorModule
    ->setId(0)
    ->setName('BrowserDetector')
;

$collection->addModule($detectorModule);

echo ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

/*******************************************************************************
 * creating full_php_browscap.ini
 */

echo 'creating full_php_browscap.ini ...';

$resourceFolder = 'vendor/browscap/browscap/resources/';

$buildNumber = (int) file_get_contents('vendor/browscap/browscap/BUILD_NUMBER');

$buildFolder = 'data/browscap-ua-test-' . $buildNumber;
$iniFile     = $buildFolder . '/full_php_browscap.ini';
$newFile     = false;

if (!file_exists($iniFile)) {
    mkdir($buildFolder, 0777, true);

    $builder = new BuildFullFileOnlyGenerator($resourceFolder, $buildFolder);
    $builder
        ->setLogger($logger)
        ->run($buildNumber, $iniFile)
    ;

    $newFile = true;
}
echo ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

/*******************************************************************************
 * Browscap-PHP
 */

echo 'initializing Browscap-PHP ...';

$browscapModule = new Browscap($logger, new File(array('dir' => 'data/cache/browscap/')));
$browscapModule
    ->setId(9)
    ->setName('Browscap-PHP')
;

$collection->addModule($browscapModule);

echo ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

/*******************************************************************************
 * Crossjoin\Browscap
 */

echo 'initializing Crossjoin\Browscap ...';

$crossjoinModule = new CrossJoin($logger, new File(array('dir' => 'data/cache/crossjoin/')), $iniFile);
$crossjoinModule
    ->setId(10)
    ->setName('Crossjoin\Browscap')
;

$collection->addModule($crossjoinModule);

echo ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

/*******************************************************************************
 * UAParser
 */

echo 'initializing UAParser ...';

$uaparserModule = new UaParser($logger, new Memory());
$uaparserModule
    ->setId(5)
    ->setName('UAParser')
;

$collection->addModule($uaparserModule);

echo ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

/*******************************************************************************
 * UASParser
 *

echo 'initializing UASParser ...';

$uasparserModule = new UasParser($logger, new Memory());
$uasparserModule
    ->setId(6)
    ->setName('UASParser')
;

$collection->addModule($uasparserModule);

echo ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

/*******************************************************************************
 * WURFL - PHP 5.3 port
 */

echo 'initializing Wurfl API (PHP-API 5.3 port) ...';

ini_set('max_input_time', '6000');
$wurflModule = new Wurfl($logger, new File(array('dir' => 'data/cache/wurfl/')), 'data/wurfl-config.xml');
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

$oldWurflModule = new WurflOld($logger, new File(array('dir' => 'data/cache/wurfl_old/')), 'data/wurfl-config.xml');
$oldWurflModule
    ->setId(7)
    ->setName('WURFL API (PHP-API 5.2 original)')
;

$collection->addModule($oldWurflModule);

echo ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

/*******************************************************************************
 * Piwik Parser
 */

echo 'initializing Piwik Parser ...';

$adapter     = new Memory();
$piwikModule = new \UaComparator\Module\PiwikDetector($logger, $adapter);
$piwikModule
    ->setId(12)
    ->setName('Piwik Parser')
;

$collection->addModule($piwikModule);

echo ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

/*******************************************************************************
 * init
 */

echo 'initializing all Modules ...';
try {
    $collection->init();
} catch (\Exception $e) {
    echo $e;
    exit;
}
echo ' - ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

/*******************************************************************************
 * Loop
 */

$i       = 1;
$count   = 0;
$aLength = ($collection->count() + 1) * (COL_LENGTH + 1);

$messageFormatter = new MessageFormatter();
$messageFormatter
    ->setCollection($collection)
    ->setColumnsLength(COL_LENGTH)
;

echo str_repeat('+', FIRST_COL_LENGTH + $aLength + $collection->count() + 1) . "\n";

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

$iterator = new \RecursiveDirectoryIterator($uaSourceDirectory);
$files    = array();
$loader   = new IniLoader();

foreach (new \RecursiveIteratorIterator($iterator) as $file) {
    /** @var $file \SplFileInfo */
    if (!$file->isFile()) {
        continue;
    }

    $files[] = $file->getPathname();
}

/*******************************************************************************
 * Loop
 */
foreach ($files as $path) {
    $loader->setLocalFile($path);
    $internalLoader = $loader->getLoader();

    if ($internalLoader->isSupportingLoadingLines()) {
        if (!$internalLoader->init($path)) {
            $logger->info('Skipping empty file "'.$file->getPathname().'"');
            continue;
        }

        while ($internalLoader->isValid()) {
            try {
                handleLine($internalLoader->getLine(), $collection, $logger, $messageFormatter, $i);
            } catch (\Exception $e) {
                if (1 === $e->getCode()) {
                    $nokfound++;
                } elseif (2 === $e->getCode()) {
                    $sosofound++;
                } else {
                    $okfound++;
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

        $internalLoader->close();
        $i--;
    } else {
        $lines = file($path);

        if (empty($lines)) {
            $logger->info('Skipping empty file "'.$file->getPathname().'"');
            continue;
        }

        foreach ($lines as $line) {
            try {
                handleLine($line, $collection, $logger, $messageFormatter, $i);
            } catch (\Exception $e) {
                if (1 === $e->getCode()) {
                    $nokfound++;
                } elseif (2 === $e->getCode()) {
                    $sosofound++;
                } else {
                    $okfound++;
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
        $i--;
    }
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

echo str_repeat('-', FIRST_COL_LENGTH) . '+' . str_repeat('-', $collection->count() + $aLength) . "\n";
echo 'Weight of Device Manufacturers' . "\n";

$weights['manufacturers'] = array_reverse($weights['manufacturers']);

foreach ($weights['manufacturers'] as $manufacturer => $weight) {
    echo substr(str_repeat(' ', $len) . $manufacturer, -1 * $len) . '|' . substr(str_repeat(' ', FIRST_COL_LENGTH) . number_format($weight, 0, ',', '.'), -1 * FIRST_COL_LENGTH) . "\n";
}

echo str_repeat('-', FIRST_COL_LENGTH) . '+' . str_repeat('-', $collection->count() + $aLength) . "\n";
echo 'Weight of Devices' . "\n";

$weights['devices'] = array_reverse($weights['devices']);

foreach ($weights['devices'] as $device => $weight) {
    echo substr(str_repeat(' ', $len) . $device, -1 * $len) . '|' . substr(str_repeat(' ', FIRST_COL_LENGTH) . number_format($weight, 0, ',', '.'), -1 * FIRST_COL_LENGTH) . "\n";
}

echo str_repeat('-', FIRST_COL_LENGTH) . '+' . str_repeat('-', $collection->count() + $aLength) . "\n";
echo 'Weight of Browsers' . "\n";

$weights['browsers'] = array_reverse($weights['browsers']);

foreach ($weights['browsers'] as $browser => $weight) {
    echo substr(str_repeat(' ', $len) . $browser, -1 * $len) . '|' . substr(str_repeat(' ', FIRST_COL_LENGTH) . number_format($weight, 0, ',', '.'), -1 * FIRST_COL_LENGTH) . "\n";
}

echo str_repeat('-', FIRST_COL_LENGTH) . '+' . str_repeat('-', $collection->count() + $aLength) . "\n";
echo 'Weight of Rendering Engines' . "\n";

$weights['engine'] = array_reverse($weights['engine']);

foreach ($weights['engine'] as $engine => $weight) {
    echo substr(str_repeat(' ', $len) . $engine, -1 * $len) . '|' . substr(str_repeat(' ', FIRST_COL_LENGTH) . number_format($weight, 0, ',', '.'), -1 * FIRST_COL_LENGTH) . "\n";
}

echo str_repeat('-', FIRST_COL_LENGTH) . '+' . str_repeat('-', $collection->count() + $aLength) . "\n";
echo 'Weight of Platforms' . "\n";

$weights['os'] = array_reverse($weights['os']);

foreach ($weights['os'] as $os => $weight) {
    echo substr(str_repeat(' ', $len) . $os, -1 * $len) . '|' . substr(str_repeat(' ', FIRST_COL_LENGTH) . number_format($weight, 0, ',', '.'), -1 * FIRST_COL_LENGTH) . "\n";
}

echo str_repeat('-', FIRST_COL_LENGTH) . '+' . str_repeat('-', $collection->count() + $aLength) . "\n";

// End
echo str_repeat('+', FIRST_COL_LENGTH + $aLength + $collection->count() - 1 + 2) . "\n";

/**
 * @param string                                $agent
 * @param \UaComparator\Module\ModuleCollection $collection
 * @param \Monolog\Logger                       $logger
 * @param \UaComparator\Helper\MessageFormatter $messageFormatter
 * @param integer                               $i
 *
 * @throws \Exception
 */
function handleLine($agent, ModuleCollection $collection, Logger $logger, MessageFormatter $messageFormatter, $i)
{
    $startTime = microtime(true);
    $ok        = true;
    $matches   = array();
    $aLength   = ($collection->count() + 1) * (COL_LENGTH + 1);

    /***************************************************************************
     * handle modules
     */

    foreach ($collection as $module) {
        $module
            ->startTimer()
            ->detect($agent)
            ->endTimer()
        ;
    }

    /***************************************************************************
     * handle modules - end
     */

    /**
     * Auswertung
     */

    $content  = str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(' ', $collection->count() - 1) . '| ' . $agent . "\n";
    $content .= str_repeat('-', FIRST_COL_LENGTH) . '+' . str_repeat('-', $collection->count() - 1) . '+' . str_repeat('-', $aLength);
    $content .= "\n";

    $content .= str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(' ', $collection->count() - 1) . '|' . str_repeat(' ', COL_LENGTH) . '|';
    foreach ($collection as $target) {
        $content .= str_pad($target->getName(), COL_LENGTH, ' ', STR_PAD_RIGHT) . '|';
    }
    $content .= "\n";

    $content .= str_pad($i, FIRST_COL_LENGTH, ' ', STR_PAD_LEFT) . '|' . str_repeat(' ', $collection->count() - 1) . '|' . str_repeat('-', COL_LENGTH) . '|';
    foreach ($collection as $target) {
        $content .= str_repeat('-', COL_LENGTH) . '|';
    }
    $content .= "\n";

    $startString = str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(' ', $collection->count() - 1) . '|';
    list($ok, $content, $matches) = $messageFormatter->formatMessage(
        $content,
        $matches,
        'Browser',
        array('getFullBrowser', array(true, Version::MAJORMINOR)),
        $startString,
        $ok
    );

    $startString = '#plus# + detected|' . str_repeat(' ', $collection->count() - 1) . '|';
    list($ok, $content, $matches) = $messageFormatter->formatMessage(
        $content,
        $matches,
        'Engine',
        array('getFullEngine', array(Version::MAJORMINOR)),
        $startString,
        $ok
    );

    $startString = '#percent1# % +|' . str_repeat(' ', $collection->count() - 1) . '|';
    list($ok, $content, $matches) = $messageFormatter->formatMessage(
        $content,
        $matches,
        'OS',
        array('getFullPlatform', array(true, Version::MAJORMINOR)),
        $startString,
        $ok
    );

    $startString = '#minus# - detected|' . str_repeat(' ', $collection->count() - 1) . '|';
    list($ok, $content, $matches) = $messageFormatter->formatMessage(
        $content,
        $matches,
        'Device',
        array('getFullDevice', array(true)),
        $startString,
        $ok
    );

    $startString = '#percent2# % -|' . str_repeat(' ', $collection->count() - 1) . '|';
    list($ok, $content, $matches) = $messageFormatter->formatMessage(
        $content,
        $matches,
        'Desktop',
        array('isDesktop'),
        $startString,
        $ok
    );

    $startString = '#soso# : detected|' . str_repeat(' ', $collection->count() - 1) . '|';
    list($ok, $content, $matches) = $messageFormatter->formatMessage(
        $content,
        $matches,
        'TV',
        array('isTvDevice'),
        $startString,
        $ok
    );

    $startString = '#percent3# % :|' . str_repeat(' ', $collection->count() - 1) . '|';
    list($ok, $content, $matches) = $messageFormatter->formatMessage(
        $content,
        $matches,
        'Mobile',
        array('isMobileDevice'),
        $startString,
        $ok
    );

    $startString = str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(' ', $collection->count() - 1) . '|';

    /*
    try {
        list($ok, $content, $matches) = $messageFormatter->formatMessage(
            $content,
            $matches,
            'WurflKey',
            'wurflKey',
            $startString,
            $ok
        );
    } catch (\InvalidArgumentException $e) {
        $logger->error($e);
        $ok = false;
    }
    /**/

    list($ok, $content, $matches) = $messageFormatter->formatMessage(
        $content,
        $matches,
        'Tablet',
        array('isTablet'),
        $startString,
        $ok
    );

    list($ok, $content, $matches) = $messageFormatter->formatMessage(
        $content,
        $matches,
        'Bot',
        array('isCrawler'),
        $startString,
        $ok
    );

    list($ok, $content, $matches) = $messageFormatter->formatMessage(
        $content,
        $matches,
        'Device Typ',
        'device_type',
        $startString,
        $ok
    );

    list($ok, $content, $matches) = $messageFormatter->formatMessage(
        $content,
        $matches,
        'Console',
        array('isConsole'),
        $startString,
        $ok
    );

    list($ok, $content, $matches) = $messageFormatter->formatMessage(
        $content,
        $matches,
        'Transcoder',
        'is_transcoder',
        $startString,
        $ok
    );

    list($ok, $content, $matches) = $messageFormatter->formatMessage(
        $content,
        $matches,
        'Syndication-Reader',
        'is_syndication_reader',
        $startString,
        $ok
    );

    list($ok, $content, $matches) = $messageFormatter->formatMessage(
        $content,
        $matches,
        'Browser Typ',
        'browser_type',
        $startString,
        $ok
    );

    list($ok, $content, $matches) = $messageFormatter->formatMessage(
        $content,
        $matches,
        'Device-Hersteller',
        'manufacturer_name',
        $startString,
        $ok
    );

    list($ok, $content, $matches) = $messageFormatter->formatMessage(
        $content,
        $matches,
        'Browser-Hersteller',
        'mobile_browser_manufacturer',
        $startString,
        $ok
    );

    list($ok, $content, $matches) = $messageFormatter->formatMessage(
        $content,
        $matches,
        'OS-Hersteller',
        'device_os_manufacturer',
        $startString,
        $ok
    );

    list($ok, $content, $matches) = $messageFormatter->formatMessage(
        $content,
        $matches,
        'Engine-Hersteller',
        'renderingengine_manufacturer',
        $startString,
        $ok
    );

    $checks = array();

    $checks['pointing_method'] = array('key' => 'pointing_method', 'include' => true);

    if (!$collection[0]->getDetectionResult()->isCrawler()
        // && false === stripos($collection[0]->getDetectionResult()->getFullDevice(true), 'general')
        && ('' !== $collection[0]->getDetectionResult()->getFullDevice(true) || '' !== $collection[0]->getDetectionResult()->getFullBrowser(true))
    ) {
        $checks['model_name'] = array('key' => 'model_name', 'include' => true);
        $checks['manufacturer_name'] = array('key' => 'manufacturer_name', 'include' => true);
        $checks['brand_name'] = array('key' => 'brand_name', 'include' => true);
        $checks['model_extra_info'] = array('key' => 'model_extra_info', 'include' => false);
        $checks['marketing_name'] = array('key' => 'marketing_name', 'include' => true);
        $checks['has_qwerty_keyboard'] = array('key' => 'has_qwerty_keyboard', 'include' => true);

        /*
        // product info
        $checks['can_skip_aligned_link_row'] = array('key' => 'can_skip_aligned_link_row', 'include' => false);
        $checks['device_claims_web_support'] = array('key' => 'device_claims_web_support', 'include' => false);
        $checks['can_assign_phone_number'] = array('key' => 'can_assign_phone_number', 'include' => true);
        //  $checks['nokia_feature_pack'] = array('key' => 'nokia_feature_pack', 'include' => false);
        // $checks['nokia_series'] = array('key' => 'nokia_series', 'include' => false);
        // $checks['nokia_edition'] = array('key' => 'nokia_edition', 'include' => false);
        // $checks['ununiqueness_handler'] = array('key' => 'ununiqueness_handler', 'include' => false);
        // $checks['uaprof'] = array('key' => 'uaprof', 'include' => false);
        // $checks['uaprof2'] = array('key' => 'uaprof2', 'include' => false);
        // $checks['uaprof3'] = array('key' => 'uaprof3', 'include' => false);
        // $checks['unique'] = array('key' => 'unique', 'include' => false);

        // display
        $checks['physical_screen_width'] = array('key' => 'physical_screen_width', 'include' => false);
        $checks['physical_screen_height'] = array('key' => 'physical_screen_height', 'include' => false);
        $checks['columns'] = array('key' => 'columns', 'include' => false);
        $checks['rows'] = array('key' => 'rows', 'include' => false);
        $checks['max_image_width'] = array('key' => 'max_image_width', 'include' => false);
        $checks['max_image_height'] = array('key' => 'max_image_height', 'include' => false);
        $checks['resolution_width'] = array('key' => 'resolution_width', 'include' => true);
        $checks['resolution_height'] = array('key' => 'resolution_height', 'include' => true);
        $checks['dual_orientation'] = array('key' => 'dual_orientation', 'include' => true);
        $checks['colors'] = array('key' => 'colors', 'include' => true);

        // markup
        $checks['utf8_support'] = array('key' => 'utf8_support', 'include' => true);
        $checks['multipart_support'] = array('key' => 'multipart_support', 'include' => true);
        // $checks['supports_background_sounds'] = array('key' => 'supports_background_sounds', 'include' => true);
        // $checks['supports_vb_script'] = array('key' => 'supports_vb_script', 'include' => true);
        // $checks['supports_java_applets'] = array('key' => 'supports_java_applets', 'include' => true);
        // $checks['supports_activex_controls'] = array('key' => 'supports_activex_controls', 'include' => true);
        $checks['preferred_markup'] = array('key' => 'preferred_markup', 'include' => true);
        $checks['html_web_3_2'] = array('key' => 'html_web_3_2', 'include' => true);
        $checks['html_web_4_0'] = array('key' => 'html_web_4_0', 'include' => true);
        $checks['html_wi_oma_xhtmlmp_1_0'] = array('key' => 'html_wi_oma_xhtmlmp_1_0', 'include' => true);
        $checks['wml_1_1'] = array('key' => 'wml_1_1', 'include' => true);
        $checks['wml_1_2'] = array('key' => 'wml_1_2', 'include' => true);
        $checks['wml_1_3'] = array('key' => 'wml_1_3', 'include' => true);
        $checks['xhtml_support_level'] = array('key' => 'xhtml_support_level', 'include' => true);
        $checks['html_wi_imode_html_1'] = array('key' => 'html_wi_imode_html_1', 'include' => true);
        $checks['html_wi_imode_html_2'] = array('key' => 'html_wi_imode_html_2', 'include' => true);
        $checks['html_wi_imode_html_3'] = array('key' => 'html_wi_imode_html_3', 'include' => true);
        $checks['html_wi_imode_html_4'] = array('key' => 'html_wi_imode_html_4', 'include' => true);
        $checks['html_wi_imode_html_5'] = array('key' => 'html_wi_imode_html_5', 'include' => true);
        $checks['html_wi_imode_htmlx_1'] = array('key' => 'html_wi_imode_htmlx_1', 'include' => true);
        $checks['html_wi_imode_htmlx_1_1'] = array('key' => 'html_wi_imode_htmlx_1_1', 'include' => true);
        $checks['html_wi_w3_xhtmlbasic'] = array('key' => 'html_wi_w3_xhtmlbasic', 'include' => true);
        $checks['html_wi_imode_compact_generic'] = array('key' => 'html_wi_imode_compact_generic', 'include' => true);
        $checks['voicexml'] = array('key' => 'voicexml', 'include' => true);

        // chtml
        $checks['chtml_table_support'] = array('key' => 'chtml_table_support', 'include' => true);
        $checks['imode_region'] = array('key' => 'imode_region', 'include' => true);
        $checks['chtml_can_display_images_and_text_on_same_line'] = array('key' => 'chtml_can_display_images_and_text_on_same_line', 'include' => true);
        $checks['chtml_displays_image_in_center'] = array('key' => 'chtml_displays_image_in_center', 'include' => true);
        $checks['chtml_make_phone_call_string'] = array('key' => 'chtml_make_phone_call_string', 'include' => true);
        $checks['emoji'] = array('key' => 'emoji', 'include' => true);

        // xhtml
        $checks['xhtml_select_as_radiobutton'] = array('key' => 'xhtml_select_as_radiobutton', 'include' => true);
        $checks['xhtml_avoid_accesskeys'] = array('key' => 'xhtml_avoid_accesskeys', 'include' => true);
        $checks['xhtml_select_as_dropdown'] = array('key' => 'xhtml_select_as_dropdown', 'include' => true);
        $checks['xhtml_supports_iframe'] = array('key' => 'xhtml_supports_iframe', 'include' => false);
        $checks['xhtml_supports_forms_in_table'] = array('key' => 'xhtml_supports_forms_in_table', 'include' => true);
        $checks['xhtmlmp_preferred_mime_type'] = array('key' => 'xhtmlmp_preferred_mime_type', 'include' => true);
        $checks['xhtml_select_as_popup'] = array('key' => 'xhtml_select_as_popup', 'include' => true);
        $checks['xhtml_honors_bgcolor'] = array('key' => 'xhtml_honors_bgcolor', 'include' => true);
        $checks['xhtml_file_upload'] = array('key' => 'xhtml_file_upload', 'include' => true);
        $checks['xhtml_preferred_charset'] = array('key' => 'xhtml_preferred_charset', 'include' => true);
        $checks['xhtml_supports_css_cell_table_coloring'] = array('key' => 'xhtml_supports_css_cell_table_coloring', 'include' => true);
        $checks['xhtml_autoexpand_select'] = array('key' => 'xhtml_autoexpand_select', 'include' => true);
        $checks['accept_third_party_cookie'] = array('key' => 'accept_third_party_cookie', 'include' => true);
        $checks['xhtml_make_phone_call_string'] = array('key' => 'xhtml_make_phone_call_string', 'include' => true);
        $checks['xhtml_allows_disabled_form_elements'] = array('key' => 'xhtml_allows_disabled_form_elements', 'include' => true);
        $checks['xhtml_supports_invisible_text'] = array('key' => 'xhtml_supports_invisible_text', 'include' => true);
        $checks['cookie_support'] = array('key' => 'cookie_support', 'include' => true);
        $checks['xhtml_send_mms_string'] = array('key' => 'xhtml_send_mms_string', 'include' => true);
        $checks['xhtml_table_support'] = array('key' => 'xhtml_table_support', 'include' => false);
        $checks['xhtml_display_accesskey'] = array('key' => 'xhtml_display_accesskey', 'include' => true);
        $checks['xhtml_can_embed_video'] = array('key' => 'xhtml_can_embed_video', 'include' => false);
        $checks['xhtml_supports_monospace_font'] = array('key' => 'xhtml_supports_monospace_font', 'include' => true);
        $checks['xhtml_supports_inline_input'] = array('key' => 'xhtml_supports_inline_input', 'include' => true);
        $checks['xhtml_document_title_support'] = array('key' => 'xhtml_document_title_support', 'include' => true);
        $checks['xhtml_support_wml2_namespace'] = array('key' => 'xhtml_support_wml2_namespace', 'include' => true);
        $checks['xhtml_readable_background_color1'] = array('key' => 'xhtml_readable_background_color1', 'include' => true);
        $checks['xhtml_format_as_attribute'] = array('key' => 'xhtml_format_as_attribute', 'include' => true);
        $checks['xhtml_supports_table_for_layout'] = array('key' => 'xhtml_supports_table_for_layout', 'include' => true);
        $checks['xhtml_readable_background_color2'] = array('key' => 'xhtml_readable_background_color2', 'include' => true);
        $checks['xhtml_send_sms_string'] = array('key' => 'xhtml_send_sms_string', 'include' => true);
        $checks['xhtml_format_as_css_property'] = array('key' => 'xhtml_format_as_css_property', 'include' => true);
        $checks['opwv_xhtml_extensions_support'] = array('key' => 'opwv_xhtml_extensions_support', 'include' => true);
        $checks['xhtml_marquee_as_css_property'] = array('key' => 'xhtml_marquee_as_css_property', 'include' => true);
        $checks['xhtml_nowrap_mode'] = array('key' => 'xhtml_nowrap_mode', 'include' => true);

        // image format
        $checks['jpg'] = array('key' => 'jpg', 'include' => true);
        $checks['gif'] = array('key' => 'gif', 'include' => true);
        $checks['bmp'] = array('key' => 'bmp', 'include' => true);
        $checks['wbmp'] = array('key' => 'wbmp', 'include' => true);
        $checks['gif_animated'] = array('key' => 'gif_animated', 'include' => true);
        $checks['png'] = array('key' => 'png', 'include' => true);
        $checks['greyscale'] = array('key' => 'greyscale', 'include' => true);
        $checks['transparent_png_index'] = array('key' => 'transparent_png_index', 'include' => true);
        $checks['epoc_bmp'] = array('key' => 'epoc_bmp', 'include' => true);
        $checks['svgt_1_1_plus'] = array('key' => 'svgt_1_1_plus', 'include' => true);
        $checks['svgt_1_1'] = array('key' => 'svgt_1_1', 'include' => true);
        $checks['transparent_png_alpha'] = array('key' => 'transparent_png_alpha', 'include' => true);
        $checks['tiff'] = array('key' => 'tiff', 'include' => true);

        // security
        $checks['https_support'] = array('key' => 'https_support', 'include' => true);

        // storage
        $checks['max_url_length_bookmark'] = array('key' => 'max_url_length_bookmark', 'include' => true);
        $checks['max_url_length_cached_page'] = array('key' => 'max_url_length_cached_page', 'include' => true);
        $checks['max_url_length_in_requests'] = array('key' => 'max_url_length_in_requests', 'include' => true);
        $checks['max_url_length_homepage'] = array('key' => 'max_url_length_homepage', 'include' => true);

        // ajax
        $checks['ajax_support_getelementbyid'] = array('key' => 'ajax_support_getelementbyid', 'include' => true);
        $checks['ajax_xhr_type'] = array('key' => 'ajax_xhr_type', 'include' => true);
        $checks['ajax_support_event_listener'] = array('key' => 'ajax_support_event_listener', 'include' => true);
        $checks['ajax_support_javascript'] = array('key' => 'ajax_support_javascript', 'include' => true);
        $checks['ajax_manipulate_dom'] = array('key' => 'ajax_manipulate_dom', 'include' => true);
        $checks['ajax_support_inner_html'] = array('key' => 'ajax_support_inner_html', 'include' => true);
        $checks['ajax_manipulate_css'] = array('key' => 'ajax_manipulate_css', 'include' => true);
        $checks['ajax_support_events'] = array('key' => 'ajax_support_events', 'include' => true);
        $checks['ajax_preferred_geoloc_api'] = array('key' => 'ajax_preferred_geoloc_api', 'include' => true);

        // pdf
        $checks['pdf_support'] = array('key' => 'pdf_support', 'include' => true);

        // third_party
        $checks['jqm_grade'] = array('key' => 'jqm_grade', 'include' => true);
        $checks['is_sencha_touch_ok'] = array('key' => 'is_sencha_touch_ok', 'include' => true);

        // html
        $checks['image_inlining'] = array('key' => 'image_inlining', 'include' => false);
        $checks['canvas_support'] = array('key' => 'canvas_support', 'include' => true);
        $checks['viewport_width'] = array('key' => 'viewport_width', 'include' => true);
        $checks['html_preferred_dtd'] = array('key' => 'html_preferred_dtd', 'include' => true);
        $checks['viewport_supported'] = array('key' => 'viewport_supported', 'include' => true);
        $checks['viewport_minimum_scale'] = array('key' => 'viewport_minimum_scale', 'include' => true);
        $checks['viewport_initial_scale'] = array('key' => 'viewport_initial_scale', 'include' => true);
        $checks['mobileoptimized'] = array('key' => 'mobileoptimized', 'include' => true);
        $checks['viewport_maximum_scale'] = array('key' => 'viewport_maximum_scale', 'include' => true);
        $checks['viewport_userscalable'] = array('key' => 'viewport_userscalable', 'include' => true);
        $checks['handheldfriendly'] = array('key' => 'handheldfriendly', 'include' => true);

        // css
        $checks['css_spriting'] = array('key' => 'css_spriting', 'include' => false);
        $checks['css_gradient'] = array('key' => 'css_gradient', 'include' => true);
        $checks['css_gradient_linear'] = array('key' => 'css_gradient_linear', 'include' => true);
        $checks['css_border_image'] = array('key' => 'css_border_image', 'include' => true);
        $checks['css_rounded_corners'] = array('key' => 'css_rounded_corners', 'include' => true);
        $checks['css_supports_width_as_percentage'] = array('key' => 'css_supports_width_as_percentage', 'include' => true);

        // bugs
        $checks['empty_option_value_support'] = array('key' => 'empty_option_value_support', 'include' => true);
        $checks['basic_authentication_support'] = array('key' => 'basic_authentication_support', 'include' => true);
        $checks['post_method_support'] = array('key' => 'post_method_support', 'include' => true);

        // rss
        $checks['rss_support'] = array('key' => 'rss_support', 'include' => true);

        // sms
        $checks['sms_enabled'] = array('key' => 'sms_enabled', 'include' => true);

        // chips
        $checks['nfc_support'] = array('key' => 'nfc_support', 'include' => false);
        /**/
    }

    foreach ($checks as $label => $x) {
        if (empty($x['key'])) {
            $key = $label;
        } else {
            $key = $x['key'];
        }

        $returnMatches = array();
        $returnContent = '';
        $returnOk      = false;

        list($returnOk, $returnContent, $returnMatches) = $messageFormatter->formatMessage(
            $returnContent,
            $returnMatches,
            $label,
            $key,
            $startString,
            $returnOk
        );

        if (!$returnOk) {
            $matches  = $matches + $returnMatches;
            $content .= $returnContent;
            $ok       = $ok && $returnOk;
        }
    }
    /**/

    if (!$ok) {
        $content = "\n" . str_repeat('-', FIRST_COL_LENGTH) . '+' . str_repeat('-', $collection->count() - 1) . '+' . str_repeat('-', $aLength) . "\n" . $content;

        $content .= str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(' ', $collection->count() - 1) . '|' . str_repeat('-', COL_LENGTH) . '|';
        foreach ($collection as $target) {
            $content .= str_repeat('-', COL_LENGTH) . '|';
        }
        $content .= "\n";
        $content .= str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(' ', $collection->count() - 1) . '|' . "\n";

        $fullTime = microtime(true) - $startTime;

        $content .= $startString . 'Time:' . "\n";
        foreach ($collection as $target) {
            $content .= $startString . '        Detection (' . $target->getName() . ')' . str_repeat(' ', 60 - strlen($target->getName())) . ':' . number_format($target->getTime(), 10, ',', '.') . ' Sek.' . "\n";
        }
        $content .= $startString . '        Complete                         :' . number_format($fullTime, 10, ',', '.') . ' Sek.' . "\n";
        $content .= $startString . '        Absolute TOTAL                   :' . TimeFormatter::formatTime(microtime(true) - START_TIME) . "\n";
    } else {
        $content = '';
    }

    if (in_array('-', $matches)) {
        $content .= '-';
    } elseif (in_array(':', $matches)) {
        $content .= ':';
    } else {
        $content .= '.';
    }

    if (($i % 100) == 0) {
        $content .= "\n";
    }

    if (in_array('-', $matches)) {
        throw new \Exception($content, 1);
    } elseif (in_array(':', $matches)) {
        throw new \Exception($content, 2);
    } else {
        throw new \Exception($content, 3);
    }
}
