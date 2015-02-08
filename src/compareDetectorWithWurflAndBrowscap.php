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
define('SECOND_COL_LENGTH', 40);

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

echo ' - ready ' . formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

/*******************************************************************************
 * Logger
 */
echo 'initializing Logger ...';

$logger = LoggerFactory::create();

echo ' - ready ' . formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

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

echo ' - ready ' . formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

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
echo ' - ready ' . formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

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

echo ' - ready ' . formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

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

echo ' - ready ' . formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

/*******************************************************************************
 * UAParser
 */

echo 'initializing UAParser ...';

$uaparserModule = new UaParser($logger, new File(array('dir' => 'data/cache/uaparser/')));
$uaparserModule
    ->setId(5)
    ->setName('UAParser')
;

$collection->addModule($uaparserModule);

echo ' - ready ' . formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

/*******************************************************************************
 * UASParser
 *

echo 'initializing UASParser ...';

$uasparserModule = new UasParser($logger, new File(array('dir' => 'data/cache/uasparser/')));
$uasparserModule
    ->setId(6)
    ->setName('UASParser')
;

$collection->addModule($uasparserModule);

echo ' - ready ' . formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

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

echo ' - ready ' . formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

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

echo ' - ready ' . formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

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
echo ' - ready ' . formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

/*******************************************************************************
 * Database
 */
echo 'initializing Database ...';

$dsn      = 'mysql:dbname=browscap;host=localhost';
$user     = 'root';
$password = '';

$adapter = new PDO($dsn, $user, $password);
$adapter->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo ' - ready ' . formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

/*******************************************************************************
 * loading Agents
 */
echo 'loading agents ...';

$i = 1;

$sql = 'SELECT DISTINCT SQL_BIG_RESULT SQL_CACHE HIGH_PRIORITY `idAgents`, `agent`, `count`, `created`, `file` '
    . 'FROM `agents`'
    //. ' WHERE `agent` LIKE "%GT-I91%"'
    . ' ORDER BY `count` DESC, `idAgents` DESC'
    // . ' LIMIT 100'
;

$stmt = $adapter->prepare($sql);
$stmt->execute();

$count   = 0;
$aLength = SECOND_COL_LENGTH + 1 + COL_LENGTH + 1 + (count($targets) * (COL_LENGTH + 1));

echo ' - ready ' . formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";
echo str_repeat('+', FIRST_COL_LENGTH + $aLength + count($targets) + 2) . "\n";

$actualMemory = memory_get_usage(true);
$oldMemery    = 0;
$okfound      = 0;
$nokfound     = 0;
$sosofound    = 0;

$weights = array(
    'manufacturers' => array(),
    'devices'       => array(),
    'browsers'      => array(),
    'engine'        => array(),
    'os'            => array()
);

echo "\n";

/*******************************************************************************
 * Loop
 */
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $colorStart = '';
    $colorEnd = '';
    reset($targets);

    $agent = trim($row['agent']);

    $content = '';
    $ok      = true;
    $id      = substr(str_repeat(' ', FIRST_COL_LENGTH) . $row['idAgents'], -(FIRST_COL_LENGTH - 2));
    $matches = array();
    $modules = array();

    /***************************************************************************
     * handle modules
     */

    foreach ($collection->getModules() as $module) {
        $module
            ->startTimer()
            ->detect($agent)
            ->endTimer()
        ;

        $modules[$module->getId()] = array(
            'name'   => $module->getName(),
            'time'   => $module->getTime(),
            'result' => $module->getDetectionResult(),
        );
    }

    $detectionBrowserDetectorTime = $modules[0]['time'];
    $detectionWurflTime           = $modules[11]['time'];
    $detectionWurflOrigTime       = $modules[7]['time'];
    $detectionGbmoTime            = $modules[9]['time'];
    $detectionCrossjoinTime       = $modules[10]['time'];
    $detectionUaparserTime        = $modules[5]['time'];
    //$detectionUasparserTime       = $modules[6]['time'];

    $browser         = $modules[0]['result'];
    $deviceOrig      = $modules[7]['result'];
    $device          = $modules[11]['result'];
    $gbmo            = $modules[9]['result'];
    $crossjoinResult = $modules[10]['result'];
    $parserResult    = $modules[5]['result'];
    //$uasParserResult = $modules[6]['result'];

    /***************************************************************************
     * handle modules - end
     */

    /**
     * Auswertung
     */

    $oldMemery = $actualMemory;
    $actualMemory = memory_get_usage(true);

    $vollBrowser = $browser->getComparationName();

    $mode = Version::MAJORMINOR;

    $startString = '#count#x found|' . str_repeat(' ', count($targets)) . '|';
    $browserOk = formatMessage(
        $content,
        $matches,
        'Browser',
        $startString,
        $browser->getFullBrowser(true, $mode),
        array(
            //$targets[4] => $gbm->getFullBrowser(true, $mode),
            $targets[9] => $gbmo->getFullBrowser(true, $mode),
            $targets[11] => $device->getFullBrowser(true, $mode),
            //$targets[7] => ($deviceOrig === null ? null : $deviceOrig->getVirtualCapability('advertised_browser')),
            //$targets[8] => ($deviceTera === null ? null : $deviceTera->getVirtualCapability('advertised_browser')),
            //$targets[1] => $gboLite->getFullBrowser(true, $mode),
            //$targets[2] => $gboNormal->getFullBrowser(true, $mode),
            //$targets[3] => $gboFull->getFullBrowser(true, $mode),
            //$targets[5] => $parserResult->getFullBrowser(true, $mode),
            //$targets[6] => $uasParserResult->getFullBrowser(true, $mode),
            $targets[10] => $crossjoinResult->getFullBrowser(true, $mode),
        ),
        $vollBrowser
    ) && $ok;
    $ok = $browserOk && $ok;

    $startString = '#plus# + detected|' . str_repeat(' ', count($targets)) . '|';
    $ok = formatMessage(
        $content,
        $matches,
        'Engine',
        $startString,
        $browser->getFullEngine($mode),
        array(
            //$targets[4] => $gbm->getFullEngine($mode),
            $targets[9] => $gbmo->getFullEngine($mode),
            $targets[11] => $device->getFullEngine($mode),
            //$targets[7] => null,
            //$targets[8] => null,
            //$targets[1] => $gboLite->getFullEngine($mode),
            //$targets[2] => $gboNormal->getFullEngine($mode),
            //$targets[3] => $gboFull->getFullEngine($mode),
            //$targets[5] => $parserResult->getFullEngine($mode),
            //$targets[6] => $uasParserResult->getFullEngine($mode),
            $targets[10] => $crossjoinResult->getFullEngine($mode),
        ),
        $vollBrowser
    ) && $ok;

    $mode = Version::MAJORMINOR | Version::IGNORE_MINOR_IF_EMPTY;

    $startString = '#percent1# % +|' . str_repeat(' ', count($targets)) . '|';
    $osOk = formatMessage(
        $content,
        $matches,
        'OS',
        $startString,
        $browser->getFullPlatform(true, $mode),
        array(
            //$targets[4] => $gbm->getFullPlatform(true, $mode),
            $targets[9] => $gbmo->getFullPlatform(true, $mode),
            $targets[11] => $device->getFullPlatform(true, $mode),
            //$targets[7] => ($deviceOrig === null ? null : $deviceOrig->getVirtualCapability('advertised_device_os')),
            //$targets[8] => ($deviceTera === null ? null : $deviceTera->getVirtualCapability('advertised_device_os')),
            //$targets[1] => null,
            //$targets[2] => null,
            //$targets[3] => $gboFull->getFullPlatform(true, $mode),
            //$targets[5] => $parserResult->getFullPlatform(true, $mode),
            //$targets[6] => $uasParserResult->getFullPlatform(true, $mode),
            $targets[10] => $crossjoinResult->getFullPlatform(true, $mode),
        ),
        $vollBrowser
    ) && $ok;
    $ok = $osOk && $ok;

    $startString = '#minus# - detected|' . str_repeat(' ', count($targets)) . '|';
    $deviceOk = formatMessage(
        $content,
        $matches,
        'Device',
        $startString,
        $browser->getFullDevice(true),
        array(
            //$targets[4] => $gbm->getFullDevice(true),
            $targets[9] => $gbmo->getFullDevice(true),
            $targets[11] => $device->getFullDevice(true),
            //$targets[7] => ($deviceOrig === null ? null : $deviceOrig->getCapability('model_name')),
            //$targets[8] => ($deviceTera === null ? null : $deviceTera->getCapability('model_name')),
            //$targets[1] => $gboLite->getFullDevice(true),
            //$targets[2] => $gboNormal->getFullDevice(true),
            //$targets[3] => $gboFull->getFullDevice(true),
            //$targets[5] => $parserResult->getFullDevice(true),
            //$targets[6] => $uasParserResult->getFullDevice(true),
            $targets[10] => $crossjoinResult->getFullDevice(true),
        ),
        $vollBrowser
    ) && $ok;
    $ok = $deviceOk && $ok;

    $startString = '#percent2# % -|' . str_repeat(' ', count($targets)) . '|';
    $ok = formatMessage(
        $content,
        $matches,
        'Desktop',
        $startString,
        $browser->getCapability('ux_full_desktop', true),
        array(
            //$targets[4] => $gbm->getCapability('ux_full_desktop'),
            $targets[9] => $gbmo->getCapability('ux_full_desktop'),
            $targets[11] => $device->getCapability('ux_full_desktop'),
            //$targets[7] => ($deviceOrig === null ? null : $deviceOrig->getVirtualCapability('is_full_desktop')),
            //$targets[8] => ($deviceTera === null ? null : $deviceTera->getVirtualCapability('is_full_desktop')),
            //$targets[1] => $gboLite->getCapability('ux_full_desktop'),
            //$targets[2] => $gboNormal->getCapability('ux_full_desktop'),
            //$targets[3] => $gboFull->getCapability('ux_full_desktop'),
            //$targets[5] => $parserResult->getCapability('ux_full_desktop'),
            //$targets[6] => $uasParserResult->getCapability('ux_full_desktop'),
            $targets[10] => $crossjoinResult->getCapability('ux_full_desktop'),
        ),
        $vollBrowser
    ) && $ok;

    $startString = '#soso# : detected|' . str_repeat(' ', count($targets)) . '|';
    $ok = formatMessage(
        $content,
        $matches,
        'TV',
        $startString,
        $browser->getCapability('is_smarttv', true),
        array(
            //$targets[4] => $gbm->getCapability('is_smarttv'),
            $targets[9] => $gbmo->getCapability('is_smarttv'),
            $targets[11] => $device->getCapability('is_smarttv'),
            //$targets[7] => ($deviceOrig === null ? null : $deviceOrig->getCapability('is_smarttv')),
            //$targets[8] => ($deviceTera === null ? null : $deviceTera->getCapability('is_smarttv')),
            //$targets[1] => $gboLite->getCapability('is_smarttv'),
            //$targets[2] => $gboNormal->getCapability('is_smarttv'),
            //$targets[3] => $gboFull->getCapability('is_smarttv'),
            //$targets[5] => $parserResult->getCapability('is_smarttv'),
            //$targets[6] => $uasParserResult->getCapability('is_smarttv'),
            $targets[10] => $crossjoinResult->getCapability('is_smarttv'),
        ),
        $vollBrowser
    ) && $ok;

    $startString = '#percent3# % :|' . str_repeat(' ', count($targets)) . '|';
    $ok = formatMessage(
        $content,
        $matches,
        'Mobile',
        $startString,
        $browser->getCapability('is_wireless_device', true),
        array(
            //$targets[4] => $gbm->getCapability('is_wireless_device'),
            $targets[9] => $gbmo->getCapability('is_wireless_device'),
            $targets[11] => $device->getCapability('is_wireless_device'),
            //$targets[7] => ($deviceOrig === null ? null : $deviceOrig->getVirtualCapability('is_mobile')),
            //$targets[8] => ($deviceTera === null ? null : $deviceTera->getVirtualCapability('is_mobile')),
            //$targets[1] => $gboLite->getCapability('is_wireless_device'),
            //$targets[2] => $gboNormal->getCapability('is_wireless_device'),
            //$targets[3] => $gboFull->getCapability('is_wireless_device'),
            //$targets[5] => $parserResult->getCapability('is_wireless_device'),
            //$targets[6] => $uasParserResult->getCapability('is_wireless_device'),
            $targets[10] => $crossjoinResult->getCapability('is_wireless_device'),
        ),
        $vollBrowser
    ) && $ok;

    $startString = str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(' ', count($targets)) . '|';
    $ok = formatMessage(
        $content,
        $matches,
        'Tablet',
        $startString,
        $browser->getCapability('is_tablet', true),
        array(
            //$targets[4] => $gbm->getCapability('is_tablet'),
            $targets[9] => $gbmo->getCapability('is_tablet'),
            $targets[11] => $device->getCapability('is_tablet'),
            //$targets[7] => ($deviceOrig === null ? null : $deviceOrig->getCapability('is_tablet')),
            //$targets[8] => ($deviceTera === null ? null : $deviceTera->getCapability('is_tablet')),
            //$targets[1] => $gboLite->getCapability('is_tablet'),
            //$targets[2] => $gboNormal->getCapability('is_tablet'),
            //$targets[3] => $gboFull->getCapability('is_tablet'),
            //$targets[5] => $parserResult->getCapability('is_tablet'),
            //$targets[6] => $uasParserResult->getCapability('is_tablet'),
            $targets[10] => $crossjoinResult->getCapability('is_tablet'),
        ),
        $vollBrowser
    ) && $ok;

    $ok = formatMessage(
        $content,
        $matches,
        'Bot',
        $startString,
        $browser->getCapability('is_bot', true),
        array(
            //$targets[4] => $gbm->getCapability('is_bot'),
            $targets[9] => $gbmo->getCapability('is_bot'),
            $targets[11] => $device->getCapability('is_bot'),
            //$targets[7] => ($deviceOrig === null ? null : $deviceOrig->getVirtualCapability('is_robot')),
            //$targets[8] => ($deviceTera === null ? null : $deviceTera->getVirtualCapability('is_robot')),
            //$targets[1] => $gboLite->getCapability('is_bot'),
            //$targets[2] => $gboNormal->getCapability('is_bot'),
            //$targets[3] => $gboFull->getCapability('is_bot'),
            //$targets[5] => $parserResult->getCapability('is_bot'),
            //$targets[6] => $uasParserResult->getCapability('is_bot'),
            $targets[10] => $crossjoinResult->getCapability('is_bot'),
        ),
        $vollBrowser
    ) && $ok;

    $ok = formatMessage(
        $content,
        $matches,
        'Device Typ',
        $startString,
        $browser->getCapability('device_type', true),
        array(
            //$targets[4] => $gbm->getCapability('device_type'),
            $targets[9] => $gbmo->getCapability('device_type'),
            $targets[11] => $device->getCapability('device_type'),
            //$targets[7] => null,
            //$targets[8] => null,
            //$targets[1] => $gboLite->getCapability('device_type'),
            //$targets[2] => $gboNormal->getCapability('device_type'),
            //$targets[3] => $gboFull->getCapability('device_type'),
            //$targets[5] => $parserResult->getCapability('device_type'),
            //$targets[6] => $uasParserResult->getCapability('device_type'),
            $targets[10] => $crossjoinResult->getCapability('device_type'),
        ),
        $vollBrowser
    ) && $ok;

    $ok = formatMessage(
        $content,
        $matches,
        'Console',
        $startString,
        $browser->getCapability('is_console', true),
        array(
            //$targets[4] => $gbm->getCapability('is_console'),
            $targets[9] => $gbmo->getCapability('is_console'),
            $targets[11] => $device->getCapability('is_console'),
            //$targets[7] => ($deviceOrig === null ? null : $deviceOrig->getCapability('is_console')),
            //$targets[8] => ($deviceTera === null ? null : $deviceTera->getCapability('is_console')),
            //$targets[1] => $gboLite->getCapability('is_console'),
            //$targets[2] => $gboNormal->getCapability('is_console'),
            //$targets[3] => $gboFull->getCapability('is_console'),
            //$targets[5] => $parserResult->getCapability('is_console'),
            //$targets[6] => $uasParserResult->getCapability('is_console'),
            $targets[10] => $crossjoinResult->getCapability('is_console'),
        ),
        $vollBrowser
    ) && $ok;

    $ok = formatMessage(
        $content,
        $matches,
        'Transcoder',
        $startString,
        $browser->getCapability('is_transcoder', true),
        array(
            //$targets[4] => $gbm->getCapability('is_transcoder'),
            $targets[9] => $gbmo->getCapability('is_transcoder'),
            $targets[11] => $device->getCapability('is_transcoder'),
            //$targets[7] => ($deviceOrig === null ? null : $deviceOrig->getCapability('is_transcoder')),
            //$targets[8] => ($deviceTera === null ? null : $deviceTera->getCapability('is_transcoder')),                //$targets[1] => $gboLite->getCapability('is_transcoder'),
            //$targets[2] => $gboNormal->getCapability('is_transcoder'),
            //$targets[3] => $gboFull->getCapability('is_transcoder'),
            //$targets[5] => $parserResult->getCapability('is_transcoder'),
            //$targets[6] => $uasParserResult->getCapability('is_transcoder'),
            $targets[10] => $crossjoinResult->getCapability('is_transcoder'),
        ),
        $vollBrowser
    ) && $ok;

    $ok = formatMessage(
        $content,
        $matches,
        'Syndication-Reader',
        $startString,
        $browser->getCapability('is_syndication_reader', true),
        array(
            //$targets[4] => $gbm->getCapability('is_syndication_reader'),
            $targets[9] => $gbmo->getCapability('is_syndication_reader'),
            $targets[11] => $device->getCapability('is_syndication_reader'),
            //$targets[7] => null,
            //$targets[8] => null,
            //$targets[1] => $gboLite->getCapability('is_syndication_reader'),
            //$targets[2] => $gboNormal->getCapability('is_syndication_reader'),
            //$targets[3] => $gboFull->getCapability('is_syndication_reader'),
            //$targets[5] => $parserResult->getCapability('is_syndication_reader'),
            //$targets[6] => $uasParserResult->getCapability('is_syndication_reader'),
            $targets[10] => $crossjoinResult->getCapability('is_syndication_reader'),
        ),
        $vollBrowser
    ) && $ok;

    $ok = formatMessage(
        $content,
        $matches,
        'Browser Typ',
        $startString,
        $browser->getCapability('browser_type', true),
        array(
            //$targets[4] => $gbm->getCapability('browser_type', false)->getName(),
            $targets[9] => $gbmo->getCapability('browser_type'),
            $targets[11] => $device->getCapability('browser_type'),
            //$targets[7] => null,
            //$targets[8] => null,
            //$targets[1] => $gboLite->getCapability('browser_type', false)->getName(),
            //$targets[2] => $gboNormal->getCapability('browser_type', false)->getName(),
            //$targets[3] => $gboFull->getCapability('browser_type', false)->getName(),
            //$targets[5] => $parserResult->getCapability('browser_type', false)->getName(),
            //$targets[6] => $uasParserResult->getCapability('browser_type', false)->getName(),
            $targets[10] => $crossjoinResult->getCapability('browser_type'),
        ),
        $vollBrowser
    ) && $ok;

    $ok = formatMessage(
        $content,
        $matches,
        'Device-Hersteller',
        $startString,
        $browser->getCapability('manufacturer_name', true),
        array(
            //$targets[4] => $gbm->getCapability('manufacturer_name'),
            $targets[9] => $gbmo->getCapability('manufacturer_name'),
            $targets[11] => $device->getCapability('manufacturer_name'),
            //$targets[7] => ($deviceOrig === null ? null : $deviceOrig->getCapability('manufacturer_name')),
            //$targets[8] => ($deviceTera === null ? null : $deviceTera->getCapability('manufacturer_name')),
            //$targets[1] => $gboLite->getCapability('manufacturer_name'),
            //$targets[2] => $gboNormal->getCapability('manufacturer_name'),
            //$targets[3] => $gboFull->getCapability('manufacturer_name'),
            //$targets[5] => $parserResult->getCapability('manufacturer_name'),
            //$targets[6] => $uasParserResult->getCapability('manufacturer_name'),
            $targets[10] => $crossjoinResult->getCapability('manufacturer_name'),
        ),
        $vollBrowser
    ) && $ok;

    $ok = formatMessage(
        $content,
        $matches,
        'Browser-Hersteller',
        $startString,
        $browser->getCapability('mobile_browser_manufacturer', true),
        array(
            //$targets[4] => $gbm->getCapability('mobile_browser_manufacturer'),
            $targets[9] => $gbmo->getCapability('mobile_browser_manufacturer'),
            $targets[11] => $device->getCapability('mobile_browser_manufacturer'),
            //$targets[7] => null,
            //$targets[8] => null,
            //$targets[1] => $gboLite->getCapability('mobile_browser_manufacturer'),
            //$targets[2] => $gboNormal->getCapability('mobile_browser_manufacturer'),
            //$targets[3] => $gboFull->getCapability('mobile_browser_manufacturer'),
            //$targets[5] => $parserResult->getCapability('mobile_browser_manufacturer'),
            //$targets[6] => $uasParserResult->getCapability('mobile_browser_manufacturer'),
            $targets[10] => $crossjoinResult->getCapability('mobile_browser_manufacturer'),
        ),
        $vollBrowser
    ) && $ok;

    $ok = formatMessage(
        $content,
        $matches,
        'OS-Hersteller',
        $startString,
        $browser->getCapability('device_os_manufacturer', true),
        array(
            //$targets[4] => $gbm->getCapability('device_os_manufacturer'),
            $targets[9] => $gbmo->getCapability('device_os_manufacturer'),
            $targets[11] => $device->getCapability('device_os_manufacturer'),
            //$targets[7] => null,
            //$targets[8] => null,
            //$targets[1] => $gboLite->getCapability('device_os_manufacturer'),
            //$targets[2] => $gboNormal->getCapability('device_os_manufacturer'),
            //$targets[3] => $gboFull->getCapability('device_os_manufacturer'),
            //$targets[5] => $parserResult->getCapability('device_os_manufacturer'),
            //$targets[6] => $uasParserResult->getCapability('device_os_manufacturer'),
            $targets[10] => $crossjoinResult->getCapability('device_os_manufacturer'),
        ),
        $vollBrowser
    ) && $ok;

    $ok = formatMessage(
        $content,
        $matches,
        'Engine-Hersteller',
        $startString,
        $browser->getCapability('renderingengine_manufacturer', true),
        array(
            //$targets[4] => $gbm->getCapability('renderingengine_manufacturer'),
            $targets[9] => $gbmo->getCapability('renderingengine_manufacturer'),
            $targets[11] => $device->getCapability('renderingengine_manufacturer'),
            //$targets[7] => null,
            //$targets[8] => null,
            //$targets[1] => $gboLite->getCapability('renderingengine_manufacturer'),
            //$targets[2] => $gboNormal->getCapability('renderingengine_manufacturer'),
            //$targets[3] => $gboFull->getCapability('renderingengine_manufacturer'),
            //$targets[5] => $parserResult->getCapability('renderingengine_manufacturer'),
            //$targets[6] => $uasParserResult->getCapability('renderingengine_manufacturer'),
            $targets[10] => $crossjoinResult->getCapability('renderingengine_manufacturer'),
        ),
        $vollBrowser
    ) && $ok;

    $matchesTemp = array();
    $checks      = array();

    $checks['pointing_method'] = array('key' => 'pointing_method', 'include' => true);

    if (!$browser->getCapability('is_bot', false)
        // && false === stripos($browser->getFullDevice(true), 'general')
        && ('' !== $browser->getFullDevice(true) || '' !== $browser->getFullBrowser(true))
    ) {
        if ($browser->getCapability('is_wireless_device', false)) {
            // $checks['DeviceClass'] = array('key' => 'deviceClass', 'include' => false);
            $checks['model_name'] = array('key' => 'model_name', 'include' => true);
            // $checks['model_version'] = array('key' => 'model_version', 'include' => false);
            $checks['manufacturer_name'] = array('key' => 'manufacturer_name', 'include' => true);
            $checks['brand_name'] = array('key' => 'brand_name', 'include' => true);
            // $checks['model_extra_info'] = array('key' => 'model_extra_info', 'include' => false);
            $checks['marketing_name'] = array('key' => 'marketing_name', 'include' => true);
            $checks['has_qwerty_keyboard'] = array('key' => 'has_qwerty_keyboard', 'include' => true);
            // $checks['device_bits'] = array('key' => 'device_bits', 'include' => false);
            // $checks['device_cpu'] = array('key' => 'device_cpu', 'include' => false);

            // product info
            // $checks['can_skip_aligned_link_row'] = array('key' => 'can_skip_aligned_link_row', 'include' => false);
            // $checks['device_claims_web_support'] = array('key' => 'device_claims_web_support', 'include' => false);
            $checks['can_assign_phone_number'] = array('key' => 'can_assign_phone_number', 'include' => true);
            // $checks['nokia_feature_pack'] = array('key' => 'nokia_feature_pack', 'include' => false);
            // $checks['nokia_series'] = array('key' => 'nokia_series', 'include' => false);
            // $checks['nokia_edition'] = array('key' => 'nokia_edition', 'include' => false);
            // $checks['ununiqueness_handler'] = array('key' => 'ununiqueness_handler', 'include' => false);
            // $checks['uaprof'] = array('key' => 'uaprof', 'include' => false);
            // $checks['uaprof2'] = array('key' => 'uaprof2', 'include' => false);
            // $checks['uaprof3'] = array('key' => 'uaprof3', 'include' => false);
            // $checks['unique'] = array('key' => 'unique', 'include' => false);

            // display
            // $checks['physical_screen_width'] = array('key' => 'physical_screen_width', 'include' => false);
            // $checks['physical_screen_height'] = array('key' => 'physical_screen_height', 'include' => false);
            // $checks['columns'] = array('key' => 'columns', 'include' => false);
            // $checks['rows'] = array('key' => 'rows', 'include' => false);
            // $checks['max_image_width'] = array('key' => 'max_image_width', 'include' => false);
            // $checks['max_image_height'] = array('key' => 'max_image_height', 'include' => false);
            $checks['resolution_width'] = array('key' => 'resolution_width', 'include' => true);
            $checks['resolution_height'] = array('key' => 'resolution_height', 'include' => true);
            $checks['dual_orientation'] = array('key' => 'dual_orientation', 'include' => true);
            $checks['colors'] = array('key' => 'colors', 'include' => true);
        } else {
            // $checks['DeviceClass'] = array('key' => 'deviceClass', 'include' => false);
        }

        // markup
        // $checks['utf8_support'] = array('key' => 'utf8_support', 'include' => true);
        // $checks['multipart_support'] = array('key' => 'multipart_support', 'include' => true);
        // $checks['supports_background_sounds'] = array('key' => 'supports_background_sounds', 'include' => true);
        // $checks['supports_vb_script'] = array('key' => 'supports_vb_script', 'include' => true);
        // $checks['supports_java_applets'] = array('key' => 'supports_java_applets', 'include' => true);
        // $checks['supports_activex_controls'] = array('key' => 'supports_activex_controls', 'include' => true);
        // $checks['preferred_markup'] = array('key' => 'preferred_markup', 'include' => true);
        // $checks['html_web_3_2'] = array('key' => 'html_web_3_2', 'include' => true);
        // $checks['html_web_4_0'] = array('key' => 'html_web_4_0', 'include' => true);
        // $checks['html_wi_oma_xhtmlmp_1_0'] = array('key' => 'html_wi_oma_xhtmlmp_1_0', 'include' => true);
        // $checks['wml_1_1'] = array('key' => 'wml_1_1', 'include' => true);
        // $checks['wml_1_2'] = array('key' => 'wml_1_2', 'include' => true);
        // $checks['wml_1_3'] = array('key' => 'wml_1_3', 'include' => true);
        // $checks['xhtml_support_level'] = array('key' => 'xhtml_support_level', 'include' => true);
        // $checks['html_wi_imode_html_1'] = array('key' => 'html_wi_imode_html_1', 'include' => true);
        // $checks['html_wi_imode_html_2'] = array('key' => 'html_wi_imode_html_2', 'include' => true);
        // $checks['html_wi_imode_html_3'] = array('key' => 'html_wi_imode_html_3', 'include' => true);
        // $checks['html_wi_imode_html_4'] = array('key' => 'html_wi_imode_html_4', 'include' => true);
        // $checks['html_wi_imode_html_5'] = array('key' => 'html_wi_imode_html_5', 'include' => true);
        // $checks['html_wi_imode_htmlx_1'] = array('key' => 'html_wi_imode_htmlx_1', 'include' => true);
        // $checks['html_wi_imode_htmlx_1_1'] = array('key' => 'html_wi_imode_htmlx_1_1', 'include' => true);
        // $checks['html_wi_w3_xhtmlbasic'] = array('key' => 'html_wi_w3_xhtmlbasic', 'include' => true);
        // $checks['html_wi_imode_compact_generic'] = array('key' => 'html_wi_imode_compact_generic', 'include' => true);
        // $checks['voicexml'] = array('key' => 'voicexml', 'include' => true);

        // chtml
        // $checks['chtml_table_support'] = array('key' => 'chtml_table_support', 'include' => true);
        // $checks['imode_region'] = array('key' => 'imode_region', 'include' => true);
        // $checks['chtml_can_display_images_and_text_on_same_line'] = array('key' => 'chtml_can_display_images_and_text_on_same_line', 'include' => true);
        // $checks['chtml_displays_image_in_center'] = array('key' => 'chtml_displays_image_in_center', 'include' => true);
        // $checks['chtml_make_phone_call_string'] = array('key' => 'chtml_make_phone_call_string', 'include' => true);
        // $checks['emoji'] = array('key' => 'emoji', 'include' => true);

        // xhtml
        // $checks['xhtml_select_as_radiobutton'] = array('key' => 'xhtml_select_as_radiobutton', 'include' => true);
        // $checks['xhtml_avoid_accesskeys'] = array('key' => 'xhtml_avoid_accesskeys', 'include' => true);
        // $checks['xhtml_select_as_dropdown'] = array('key' => 'xhtml_select_as_dropdown', 'include' => true);
        // $checks['xhtml_supports_iframe'] = array('key' => 'xhtml_supports_iframe', 'include' => false);
        // $checks['xhtml_supports_forms_in_table'] = array('key' => 'xhtml_supports_forms_in_table', 'include' => true);
        // $checks['xhtmlmp_preferred_mime_type'] = array('key' => 'xhtmlmp_preferred_mime_type', 'include' => true);
        // $checks['xhtml_select_as_popup'] = array('key' => 'xhtml_select_as_popup', 'include' => true);
        // $checks['xhtml_honors_bgcolor'] = array('key' => 'xhtml_honors_bgcolor', 'include' => true);
        // $checks['xhtml_file_upload'] = array('key' => 'xhtml_file_upload', 'include' => true);
        // $checks['xhtml_preferred_charset'] = array('key' => 'xhtml_preferred_charset', 'include' => true);
        // $checks['xhtml_supports_css_cell_table_coloring'] = array('key' => 'xhtml_supports_css_cell_table_coloring', 'include' => true);
        // $checks['xhtml_autoexpand_select'] = array('key' => 'xhtml_autoexpand_select', 'include' => true);
        // $checks['accept_third_party_cookie'] = array('key' => 'accept_third_party_cookie', 'include' => true);
        // $checks['xhtml_make_phone_call_string'] = array('key' => 'xhtml_make_phone_call_string', 'include' => true);
        // $checks['xhtml_allows_disabled_form_elements'] = array('key' => 'xhtml_allows_disabled_form_elements', 'include' => true);
        // $checks['xhtml_supports_invisible_text'] = array('key' => 'xhtml_supports_invisible_text', 'include' => true);
        // $checks['cookie_support'] = array('key' => 'cookie_support', 'include' => true);
        // $checks['xhtml_send_mms_string'] = array('key' => 'xhtml_send_mms_string', 'include' => true);
        // $checks['xhtml_table_support'] = array('key' => 'xhtml_table_support', 'include' => false);
        // $checks['xhtml_display_accesskey'] = array('key' => 'xhtml_display_accesskey', 'include' => true);
        // $checks['xhtml_can_embed_video'] = array('key' => 'xhtml_can_embed_video', 'include' => false);
        // $checks['xhtml_supports_monospace_font'] = array('key' => 'xhtml_supports_monospace_font', 'include' => true);
        // $checks['xhtml_supports_inline_input'] = array('key' => 'xhtml_supports_inline_input', 'include' => true);
        // $checks['xhtml_document_title_support'] = array('key' => 'xhtml_document_title_support', 'include' => true);
        // $checks['xhtml_support_wml2_namespace'] = array('key' => 'xhtml_support_wml2_namespace', 'include' => true);
        // $checks['xhtml_readable_background_color1'] = array('key' => 'xhtml_readable_background_color1', 'include' => true);
        // $checks['xhtml_format_as_attribute'] = array('key' => 'xhtml_format_as_attribute', 'include' => true);
        // $checks['xhtml_supports_table_for_layout'] = array('key' => 'xhtml_supports_table_for_layout', 'include' => true);
        // $checks['xhtml_readable_background_color2'] = array('key' => 'xhtml_readable_background_color2', 'include' => true);
        // $checks['xhtml_send_sms_string'] = array('key' => 'xhtml_send_sms_string', 'include' => true);
        // $checks['xhtml_format_as_css_property'] = array('key' => 'xhtml_format_as_css_property', 'include' => true);
        // $checks['opwv_xhtml_extensions_support'] = array('key' => 'opwv_xhtml_extensions_support', 'include' => true);
        // $checks['xhtml_marquee_as_css_property'] = array('key' => 'xhtml_marquee_as_css_property', 'include' => true);
        // $checks['xhtml_nowrap_mode'] = array('key' => 'xhtml_nowrap_mode', 'include' => true);

        // image format
        // $checks['jpg'] = array('key' => 'jpg', 'include' => true);
        // $checks['gif'] = array('key' => 'gif', 'include' => true);
        // $checks['bmp'] = array('key' => 'bmp', 'include' => true);
        // $checks['wbmp'] = array('key' => 'wbmp', 'include' => true);
        // $checks['gif_animated'] = array('key' => 'gif_animated', 'include' => true);
        // $checks['png'] = array('key' => 'png', 'include' => true);
        // $checks['greyscale'] = array('key' => 'greyscale', 'include' => true);
        // $checks['transparent_png_index'] = array('key' => 'transparent_png_index', 'include' => true);
        // $checks['epoc_bmp'] = array('key' => 'epoc_bmp', 'include' => true);
        // $checks['svgt_1_1_plus'] = array('key' => 'svgt_1_1_plus', 'include' => true);
        // $checks['svgt_1_1'] = array('key' => 'svgt_1_1', 'include' => true);
        // $checks['transparent_png_alpha'] = array('key' => 'transparent_png_alpha', 'include' => true);
        // $checks['tiff'] = array('key' => 'tiff', 'include' => true);

        // security
        // $checks['https_support'] = array('key' => 'https_support', 'include' => true);

        // storage
        // $checks['max_url_length_bookmark'] = array('key' => 'max_url_length_bookmark', 'include' => true);
        // $checks['max_url_length_cached_page'] = array('key' => 'max_url_length_cached_page', 'include' => true);
        // $checks['max_url_length_in_requests'] = array('key' => 'max_url_length_in_requests', 'include' => true);
        // $checks['max_url_length_homepage'] = array('key' => 'max_url_length_homepage', 'include' => true);

        // ajax
        // $checks['ajax_support_getelementbyid'] = array('key' => 'ajax_support_getelementbyid', 'include' => true);
        // $checks['ajax_xhr_type'] = array('key' => 'ajax_xhr_type', 'include' => true);
        // $checks['ajax_support_event_listener'] = array('key' => 'ajax_support_event_listener', 'include' => true);
        // $checks['ajax_support_javascript'] = array('key' => 'ajax_support_javascript', 'include' => true);
        // $checks['ajax_manipulate_dom'] = array('key' => 'ajax_manipulate_dom', 'include' => true);
        // $checks['ajax_support_inner_html'] = array('key' => 'ajax_support_inner_html', 'include' => true);
        // $checks['ajax_manipulate_css'] = array('key' => 'ajax_manipulate_css', 'include' => true);
        // $checks['ajax_support_events'] = array('key' => 'ajax_support_events', 'include' => true);
        // $checks['ajax_preferred_geoloc_api'] = array('key' => 'ajax_preferred_geoloc_api', 'include' => true);

        // pdf
        // $checks['pdf_support'] = array('key' => 'pdf_support', 'include' => true);

        // third_party
        // $checks['jqm_grade'] = array('key' => 'jqm_grade', 'include' => true);
        // $checks['is_sencha_touch_ok'] = array('key' => 'is_sencha_touch_ok', 'include' => true);

        // html
        // $checks['image_inlining'] = array('key' => 'image_inlining', 'include' => false);
        // $checks['canvas_support'] = array('key' => 'canvas_support', 'include' => true);
        // $checks['viewport_width'] = array('key' => 'viewport_width', 'include' => true);
        // $checks['html_preferred_dtd'] = array('key' => 'html_preferred_dtd', 'include' => true);
        // $checks['viewport_supported'] = array('key' => 'viewport_supported', 'include' => true);
        // $checks['viewport_minimum_scale'] = array('key' => 'viewport_minimum_scale', 'include' => true);
        // $checks['viewport_initial_scale'] = array('key' => 'viewport_initial_scale', 'include' => true);
        // $checks['mobileoptimized'] = array('key' => 'mobileoptimized', 'include' => true);
        // $checks['viewport_maximum_scale'] = array('key' => 'viewport_maximum_scale', 'include' => true);
        // $checks['viewport_userscalable'] = array('key' => 'viewport_userscalable', 'include' => true);
        // $checks['handheldfriendly'] = array('key' => 'handheldfriendly', 'include' => true);

        // css
        // $checks['css_spriting'] = array('key' => 'css_spriting', 'include' => false);
        // $checks['css_gradient'] = array('key' => 'css_gradient', 'include' => true);
        // $checks['css_gradient_linear'] = array('key' => 'css_gradient_linear', 'include' => true);
        // $checks['css_border_image'] = array('key' => 'css_border_image', 'include' => true);
        // $checks['css_rounded_corners'] = array('key' => 'css_rounded_corners', 'include' => true);
        // $checks['css_supports_width_as_percentage'] = array('key' => 'css_supports_width_as_percentage', 'include' => true);

        // bugs
        // $checks['empty_option_value_support'] = array('key' => 'empty_option_value_support', 'include' => true);
        // $checks['basic_authentication_support'] = array('key' => 'basic_authentication_support', 'include' => true);
        // $checks['post_method_support'] = array('key' => 'post_method_support', 'include' => true);

        // rss
        // $checks['rss_support'] = array('key' => 'rss_support', 'include' => true);

        // sms
        // $checks['sms_enabled'] = array('key' => 'sms_enabled', 'include' => true);

        // chips
        // $checks['nfc_support'] = array('key' => 'nfc_support', 'include' => false);


    }

    foreach ($checks as $label => $x) {
        if (empty($x['include'])) {
            $include = false;
        } else {
            $include = ($x['include'] ? true : false);
        }

        if (empty($x['key'])) {
            $key = $label;
        } else {
            $key = $x['key'];
        }

        $returnMatches = array();

        $return = formatMessage(
            $content,
            $returnMatches,
            $label,
            $startString,
            $browser->getCapability($key, true),
            array(
                //$targets[4] => $gbm->getCapability($key),
                $targets[9] => $gbmo->getCapability($key),
                $targets[11] => $device->getCapability($key),
                //$targets[7] => ($deviceOrig === null ? null : $deviceOrig->getCapability($key)),
                //$targets[8] => ($deviceTera === null ? null : $deviceTera->getCapability($key)),
                //$targets[1] => $gboLite->getCapability($key),
                //$targets[2] => $gboNormal->getCapability($key),
                //$targets[3] => $gboFull->getCapability($key),
                //$targets[5] => $parserResult->getCapability($key),
                //$targets[6] => $uasParserResult->getCapability($key),
                $targets[10] => $crossjoinResult->getCapability($key),
            ),
            $vollBrowser
        );

        if ($include) {
            $ok      = $return && $ok;
            $matches = $matches + $returnMatches;
        }
    }

    if (in_array('-', $matches)) {
        $nokfound++;
    } elseif (in_array(':', $matches)) {
        $sosofound++;
    } else {
        $okfound++;
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
            str_pad(number_format($row['count'], 0, ',', '.'), FIRST_COL_LENGTH - 7, ' ', STR_PAD_LEFT),
            str_pad($okfound, FIRST_COL_LENGTH - 11, ' ', STR_PAD_LEFT) ,
            str_pad($nokfound, FIRST_COL_LENGTH - 11, ' ', STR_PAD_LEFT),
            str_pad($sosofound, FIRST_COL_LENGTH - 11, ' ', STR_PAD_LEFT),
            str_pad(number_format((100 * $okfound / $i), 9, ',', '.'), FIRST_COL_LENGTH - 4, ' ', STR_PAD_LEFT),
            str_pad(number_format((100 * $nokfound / $i), 9, ',', '.'), FIRST_COL_LENGTH - 4, ' ', STR_PAD_LEFT),
            str_pad(number_format((100 * $sosofound / $i), 9, ',', '.'), FIRST_COL_LENGTH - 4, ' ', STR_PAD_LEFT),
        ),
        $content
    );

    if (!$ok
        // || ($i <= 5)
        || (false !== stripos($browser->getCapability('mobile_browser'), 'general'))
        || (false !== stripos($browser->getCapability('mobile_browser'), 'unknown'))
    ) {
        echo "\n";
        echo str_repeat('-', FIRST_COL_LENGTH) . '+' . str_repeat('-', count($targets)) . '+' . str_repeat('-', $aLength) . "\n";

        reset($targets);
        echo $colorStart . str_pad($i, FIRST_COL_LENGTH, ' ', STR_PAD_LEFT) . '|' . str_repeat('-', count($targets)) . '|' . str_repeat('-', SECOND_COL_LENGTH) . '|' . str_repeat('-', COL_LENGTH) . '|';
        foreach ($targets as $target) {
            echo str_repeat('-', COL_LENGTH) . '|';
        }
        echo $colorEnd . "\n";

        reset($targets);
        echo $colorStart . 'ID' . $id . '|' . str_repeat(' ', count($targets)) . '| ' . $agent . $colorEnd . "\n";
        echo $colorStart . str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(' ', count($targets)) . '| found last   : ' . $row['created'] . $colorEnd . "\n";
        echo $colorStart . str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(' ', count($targets)) . '| found in File: ' . $row['file'] . $colorEnd . "\n";

        echo $colorStart . str_pad($i, FIRST_COL_LENGTH, ' ', STR_PAD_LEFT) . '|' . str_repeat(' ', count($targets)) . '|' . str_repeat(' ', SECOND_COL_LENGTH) . '|' . str_pad('UserAgent', COL_LENGTH, ' ', STR_PAD_RIGHT) . '|';
        $tagetTitles = array(
            //$targets[4],
            $targets[9],
            $targets[11],
            //$targets[7],
            //$targets[8],
            //$targets[1],
            //$targets[2],
            //$targets[3],
            //$targets[5],
            //$targets[6],
            $targets[10],
        );
        foreach ($tagetTitles as $target) {
            echo str_pad($target, COL_LENGTH, ' ', STR_PAD_RIGHT) . '|';
        }
        echo $colorEnd . "\n";

        reset($targets);
        echo $colorStart . str_pad($i, FIRST_COL_LENGTH, ' ', STR_PAD_LEFT) . '|' . str_pad(($ok ? '+' : '-'), count($targets), ' ') . '|' . str_repeat('-', SECOND_COL_LENGTH) . '|' . str_repeat('-', COL_LENGTH) . '|';
        foreach ($targets as $target) {
            echo str_repeat('-', COL_LENGTH) . '|';
        }
        echo $colorEnd . "\n";

        echo $content;

        reset($targets);
        echo $colorStart . str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(' ', count($targets)) . '|' . str_repeat('-', SECOND_COL_LENGTH) . '|' . str_repeat('-', COL_LENGTH) . '|';
        foreach ($targets as $target) {
            echo str_repeat('-', COL_LENGTH) . '|';
        }
        echo $colorEnd . "\n";
        echo $colorStart . str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(' ', count($targets)) . '|' . $colorEnd . "\n";

        $fullTime = microtime(true) - $startTime;

        echo $startString . 'Time:   Detection (BrowserDetectorModule)' . str_repeat(' ', 60 - strlen('BrowserDetectorModule')) . ':' . number_format($detectionBrowserDetectorTime, 10, ',', '.') . ' Sek.' . "\n";
        echo $startString . '        Detection (' . $targets[11] . ')' . str_repeat(' ', 60 - strlen($targets[11])) . ':' . number_format($detectionWurflTime, 10, ',', '.') . ' Sek.' . "\n";
        // echo $startString . '        Detection (' . $targets[7] . ')' . str_repeat(' ', 60 - strlen($targets[7])) . ':' . number_format($detectionWurflOrigTime, 10, ',', '.') . ' Sek.' . "\n";
        // echo $startString . '        Detection (' . $targets[8] . ')' . str_repeat(' ', 60 - strlen($targets[8])) . ':' . number_format($detectionWurflTeraTime, 10, ',', '.') . ' Sek.' . "\n";
        // echo $startString . '        Detection (' . $targets[1] . ')' . str_repeat(' ', 60 - strlen($targets[1])) . ':' . number_format($detectionGboLiteTime, 10, ',', '.') . ' Sek.' . "\n";
        // echo $startString . '        Detection (' . $targets[2] . ')' . str_repeat(' ', 60 - strlen($targets[2])) . ':' . number_format($detectionGboNormalTime, 10, ',', '.') . ' Sek.' . "\n";
        // echo $startString . '        Detection (' . $targets[3] . ')' . str_repeat(' ', 60 - strlen($targets[3])) . ':' . number_format($detectionGboFullTime, 10, ',', '.') . ' Sek.' . "\n";
        // echo $startString . '        Detection (' . $targets[4] . ')' . str_repeat(' ', 60 - strlen($targets[4])) . ':' . number_format($detectionGbmTime, 10, ',', '.') . ' Sek.' . "\n";
        echo $startString . '        Detection (' . $targets[9] . ')' . str_repeat(' ', 60 - strlen($targets[9])) . ':' . number_format($detectionGbmoTime, 10, ',', '.') . ' Sek.' . "\n";
        // echo $startString . '        Detection (' . $targets[5] . ')' . str_repeat(' ', 60 - strlen($targets[5])) . ':' . number_format($detectionUaparserTime, 10, ',', '.') . ' Sek.' . "\n";
        // echo $startString . '        Detection (' . $targets[6] . ')' . str_repeat(' ', 60 - strlen($targets[6])) . ':' . number_format($detectionUasparserTime, 10, ',', '.') . ' Sek.' . "\n";
        echo $startString . '        Detection (' . $targets[10] . ')' . str_repeat(' ', 60 - strlen($targets[10])) . ':' . number_format($detectionCrossjoinTime, 10, ',', '.') . ' Sek.' . "\n";
        echo $startString . '        Complete                         :' . number_format($fullTime, 10, ',', '.') . ' Sek.' . "\n";
        $totalTime = microtime(true) - START_TIME;
        echo $startString . '        Absolute TOTAL                   :' . formatTime(microtime(true) - START_TIME) . "\n";
        echo $startString . 'Memory: ' . number_format($actualMemory, 0, ',', '.') . ' Bytes [Diff:' . number_format($actualMemory - $oldMemery, 0, ',', '.') . ' Bytes]' . "\n";
    }

    $man = $browser->getCapability('manufacturer_name');
    $dev = $browser->getCapability('brand_name') . ' ' . $browser->getCapability('model_name');
    $bro = $browser->getCapability('mobile_browser_brand_name') . ' ' . $browser->getCapability('mobile_browser');
    $os = $browser->getCapability('device_os_brand_name') . ' ' . $browser->getCapability('device_os');
    $engine = $browser->getCapability('renderingengine_brand_name') . ' ' . $browser->getCapability('renderingengine_name');

    if (!isset($weights['manufacturers'][$man])) {
        $weights['manufacturers'][$man] = 0;
    }

    if (!isset($weights['devices'][$dev])) {
        $weights['devices'][$dev] = 0;
    }

    if (!isset($weights['browsers'][$bro])) {
        $weights['browsers'][$bro] = 0;
    }

    if (!isset($weights['engine'][$engine])) {
        $weights['engine'][$engine] = 0;
    }

    if (!isset($weights['os'][$os])) {
        $weights['os'][$os] = 0;
    }

    $weights['manufacturers'][$man] += $row['count'];
    $weights['devices'][$dev]       += $row['count'];
    $weights['browsers'][$bro]      += $row['count'];
    $weights['engine'][$engine]     += $row['count'];
    $weights['os'][$os]             += $row['count'];

    unset(
        $row,
        $startString,
        $fullTime,
        $colorEnd,
        $colorStart,
        $startTime,
        $agent,
        $content,
        $ok,
        $id,
        //$matches,
        $detectionStartTime,
        $wurflStartTime,
        $wurflConfig,
        $wurflCache,
        $wurflStorage,
        $wurflManager,
        $device,
        $detectionWurflTime,
        $wurflOrigStartTime,
        $wurflConfigOrig,
        $wurflCacheOrig,
        $wurflStorageOrig,
        $wurflManagerFactoryOrig,
        $wurflManagerOrig,
        $deviceOrig,
        $detectionWurflOrigTime,
        $oldMemery,
        $vollBrowser,
        $allCapabilities
    );

    if ($nokfound >= 5000) {
        break;
    }

    //echo '.';}

    if (in_array('-', $matches)) {
        echo '-';
    } elseif (in_array(':', $matches)) {
        echo ':';
    } else {
        echo '.';
    }
    unset($matches);

    if (($i % 100) == 0) {
        echo "\n";
    }

    ++$i;
}

echo "\n" . str_repeat('-', FIRST_COL_LENGTH) . '+' . str_repeat('-', count($targets)) . '+' . str_repeat('-', $aLength) . "\n";

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

$len = FIRST_COL_LENGTH + SECOND_COL_LENGTH;

asort($weights['manufacturers'], SORT_NUMERIC);
asort($weights['devices'], SORT_NUMERIC);
asort($weights['browsers'], SORT_NUMERIC);
asort($weights['engine'], SORT_NUMERIC);
asort($weights['os'], SORT_NUMERIC);

echo str_repeat('-', FIRST_COL_LENGTH) . '+' . str_repeat('-', count($targets) + $aLength + 1) . "\n";
echo 'Weight of Device Manufacturers' . "\n";

$weights['manufacturers'] = array_reverse($weights['manufacturers']);

foreach ($weights['manufacturers'] as $manufacturer => $weight) {
    echo substr(str_repeat(' ', $len) . $manufacturer, -1 * $len) . '|' . substr(str_repeat(' ', FIRST_COL_LENGTH) . number_format($weight, 0, ',', '.'), -1 * FIRST_COL_LENGTH) . "\n";
}

echo str_repeat('-', FIRST_COL_LENGTH) . '+' . str_repeat('-', count($targets) + $aLength + 1) . "\n";
echo 'Weight of Devices' . "\n";

$weights['devices'] = array_reverse($weights['devices']);

foreach ($weights['devices'] as $device => $weight) {
    echo substr(str_repeat(' ', $len) . $device, -1 * $len) . '|' . substr(str_repeat(' ', FIRST_COL_LENGTH) . number_format($weight, 0, ',', '.'), -1 * FIRST_COL_LENGTH) . "\n";
}

echo str_repeat('-', FIRST_COL_LENGTH) . '+' . str_repeat('-', count($targets) + $aLength + 1) . "\n";
echo 'Weight of Browsers' . "\n";

$weights['browsers'] = array_reverse($weights['browsers']);

foreach ($weights['browsers'] as $browser => $weight) {
    echo substr(str_repeat(' ', $len) . $browser, -1 * $len) . '|' . substr(str_repeat(' ', FIRST_COL_LENGTH) . number_format($weight, 0, ',', '.'), -1 * FIRST_COL_LENGTH) . "\n";
}

echo str_repeat('-', FIRST_COL_LENGTH) . '+' . str_repeat('-', count($targets) + $aLength + 1) . "\n";
echo 'Weight of Rendering Engines' . "\n";

$weights['engine'] = array_reverse($weights['engine']);

foreach ($weights['engine'] as $engine => $weight) {
    echo substr(str_repeat(' ', $len) . $engine, -1 * $len) . '|' . substr(str_repeat(' ', FIRST_COL_LENGTH) . number_format($weight, 0, ',', '.'), -1 * FIRST_COL_LENGTH) . "\n";
}

echo str_repeat('-', FIRST_COL_LENGTH) . '+' . str_repeat('-', count($targets) + $aLength + 1) . "\n";
echo 'Weight of Platforms' . "\n";

$weights['os'] = array_reverse($weights['os']);

foreach ($weights['os'] as $os => $weight) {
    echo substr(str_repeat(' ', $len) . $os, -1 * $len) . '|' . substr(str_repeat(' ', FIRST_COL_LENGTH) . number_format($weight, 0, ',', '.'), -1 * FIRST_COL_LENGTH) . "\n";
}

echo str_repeat('-', FIRST_COL_LENGTH) . '+' . str_repeat('-', count($targets) + $aLength + 1) . "\n";

// End
echo str_repeat('+', FIRST_COL_LENGTH + $aLength + count($targets) + 2) . "\n";

function formatMessage(&$content, &$matches, $property, $start, $reality, array $targets, $browser)
{
    static $allErrors = array();

    $startcolor = COLOR_START_GREEN;
    $endcolor   = COLOR_END;
    $mismatch   = false;
    $passed     = true;
    $start      = substr($start, 0, -1 * (2 + count($targets)));
    $testresult = '|';
    $property   = trim($property);

    $detectionMessage = array(0 => '');

    if (null === $reality || 'null' === $reality) {
        $strReality = '(NULL)';
    } elseif ('' === $reality) {
        $strReality = '(empty)';
    } elseif (false === $reality || 'false' === $reality) {
        $strReality = '(false)';
    } elseif (true === $reality || 'true' === $reality) {
        $strReality = '(true)';
    } else {
        $strReality = (string) $reality;
    }

    $tooLong = false;

    foreach ($targets as $targetName => $target) {
        $r = ' ';

        if (null === $target || 'null' === $target) {
            $strTarget = '(NULL)';
        } elseif ('' === $target) {
            $strTarget = '(empty)';
        } elseif (false === $target || 'false' === $target) {
            $strTarget = '(false)';
        } elseif (true === $target || 'true' === $target) {
            $strTarget = '(true)';
        } else {
            $strTarget = (string) $target;
        }

        if (strtolower($strTarget) === strtolower($strReality)) {
            $r  = '+';
            $r1 = '+';
        } elseif (((null === $reality) || ('' === $reality) || ('' === $strReality)) && ((null === $target) || ('' === $target))) {
            $r  = ' '; //'?';
            $r1 = '?';
        } elseif ((null === $target) || ('' === $target) || ('' === $strTarget)) {
            $r  = ' '; //'%';
            $r1 = '%';
        } else {
            $mismatch = true;
            //$passed = false;
            $startcolor = COLOR_START_RED;

            if ((strlen($strTarget) > strlen($strReality))
                && (0 < strlen($strReality))
                && (0 === strpos($strTarget, $strReality))
            ) {
                $r  = ' '; //'<';
                $r1 = '<';
            } elseif ((strlen($strTarget) < strlen($strReality))
                && (0 < strlen($strTarget))
                && (0 === strpos($strReality, $strTarget))
            ) {
                $r  = ' '; //'>';
                $r1 = '>';
            } elseif (isset($allErrors[$targetName][$browser][$property])) {
                //$passed = false;
                $r  = ':';
                $r1 = ':';
            } else {
                $passed = false;
                $r      = '-';
                $r1     = '-';
            }
        }

        $testresult .= $r;
        $matches[]   = $r;

        if (!isset($allErrors[$targetName][$browser][$property])
            && $mismatch
        ) {
            $allErrors[$targetName][$browser][$property] = $reality;
        }

        $prefix  = $r1;
        $tooLong = $tooLong || (strlen($strTarget) > COL_LENGTH);

        $detectionMessage[] = str_pad($prefix . $strTarget, COL_LENGTH, ' ') . '|';
    }

    $prefix  = ' ';
    $tooLong = $tooLong || (strlen($strReality) > COL_LENGTH);
    if ($tooLong) {
        $startcolor = COLOR_START_RED;
    }

    $detectionMessage[0] = str_pad($prefix . $strReality, COL_LENGTH, ' ') . '|';

    $start .= $testresult . '|';

    if (true || false !== strpos('WINNT', PHP_OS)) {
        $startcolor = '';
        $endcolor = '';
    }

    $content .= $startcolor . $start . substr(str_repeat(' ', SECOND_COL_LENGTH)
        . $property, -1 * SECOND_COL_LENGTH) . '|' . implode('', $detectionMessage) . $endcolor
        . "\n";

    return $passed;
}

function formatTime($time)
{
    $wochen = bcdiv((int)$time, 604800, 0);
    $restwoche = bcmod((int)$time, 604800);
    $tage = bcdiv($restwoche, 86400, 0);
    $resttage = bcmod($restwoche, 86400);
    $stunden = bcdiv($resttage, 3600, 0);
    $reststunden = bcmod($resttage, 3600);
    $minuten = bcdiv($reststunden, 60, 0);
    $sekunden = bcmod($reststunden, 60);

    return substr('00' . $wochen, -2) . ' Wochen '
        . substr('00' . $tage, -2) . ' Tage '
        . substr('00' . $stunden, -2) . ' Stunden '
        . substr('00' . $minuten, -2) . ' Minuten '
        . substr('00' . $sekunden, -2) . ' Sekunden';
}
