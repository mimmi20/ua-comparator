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

use Browscap\Generator\BuildGenerator;
use Browscap\Writer\Factory\FullPhpWriterFactory;
use UaComparator\Helper\Check;
use UaComparator\Helper\LineHandler;
use UaComparator\Helper\LoggerFactory;
use UaComparator\Module\Browscap;
use UaComparator\Module\BrowserDetectorModule;
use UaComparator\Module\CrossJoin;
use UaComparator\Module\ModuleCollection;
use UaComparator\Module\UaParser;
use UaComparator\Source\DirectorySource;
use UaComparator\Source\PdoSource;
use WurflCache\Adapter\File;
use WurflCache\Adapter\Memory;
use UaComparator\Helper\MessageFormatter;
use UaComparator\Helper\TimeFormatter;

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

$buildFolder = 'build/build-' . $buildNumber;
$iniFile     = $buildFolder . '/full_php_browscap.ini';
$newFile     = false;

if (!file_exists($iniFile) || !file_get_contents($iniFile)) {
    mkdir($buildFolder, 0777, true);

    $collectionCreator = new \Browscap\Helper\CollectionCreator();

    $writerCollectionFactory = new FullPhpWriterFactory();
    $writerCollection        = $writerCollectionFactory->createCollection($logger, $buildFolder, $iniFile);

    // Generate the actual browscap.ini files
    $buildGenerator = new BuildGenerator($resourceFolder, $buildFolder);
    $buildGenerator
        ->setLogger($logger)
        ->setCollectionCreator($collectionCreator)
        ->setWriterCollection($writerCollection)
        ->run($buildNumber)
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

$dsn      = 'mysql:dbname=browscap;host=localhost';
$user     = 'root';
$password = '';

try {
    $adapter = new PDO(
        $dsn,
        $user,
        $password,
        array(
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'',
            1005 => 1024 * 1024 * 50, // PDO::MYSQL_ATTR_MAX_BUFFER_SIZE
        )
    );
    $adapter->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $source = new PdoSource($adapter);
} catch (\Exception $e) {
    $logger->debug($e);

    $uaSourceDirectory = 'data/useragents';
    $source            = new DirectorySource($uaSourceDirectory);
}

$lineHandler = new LineHandler();
$checkHelper = new Check();
$checks      = $checkHelper->getChecks(Check::MINIMUM, $collection);

/*******************************************************************************
 * Loop
 */
foreach ($source->getUserAgents($logger) as $line) {
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
