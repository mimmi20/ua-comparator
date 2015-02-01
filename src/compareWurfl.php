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

use Monolog\ErrorHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\MemoryPeakUsageProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\WebProcessor;
use UaComparator\Helper\LoggerFactory;
use UaComparator\Module\Wurfl;
use UaComparator\Module\WurflOld;
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

/**
 * @var \UaComparator\Module\ModuleInterface[] $modules
 */
$modules = array();

echo ' - ready ' . formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

/*******************************************************************************
 * Logger
 */
echo 'initializing Logger ...';

$logger = LoggerFactory::create();

echo ' - ready ' . formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

/*******************************************************************************
 * WURFL - PHP 5.3 port
 */

echo 'initializing Wurfl API (PHP-API 5.3 port) ...';

// Create WURFL Configuration from an XML config file
ini_set('max_input_time', '6000');
$adapter     = new File(array('dir' => 'data/cache/wurfl/'));
$wurflModule = new Wurfl($logger, $adapter, 'data/wurfl-config.xml');

$wurflModule->init();

$targets[11] = 'WURFL API (PHP-API 5.3)';
$modules[11] = $wurflModule;

$target = 'WURFL API (PHP-API 5.3)';

echo ' - ready ' . formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes' . "\n";

/*******************************************************************************
 * WURFL - PHP 5.2 original
 */

echo 'initializing Wurfl API (PHP-API 5.2 original) ...';

// Create WURFL Configuration from an XML config file
$adapter        = new File(array('dir' => 'data/cache/wurfl_old/'));
$oldWurflModule = new WurflOld($logger, $adapter, 'data/wurfl-config.xml');

$oldWurflModule->init();

$targets[7] = 'WURFL API (PHP-API 5.2 original)';
$modules[7] = $oldWurflModule;

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

    $startTime = microtime(true);

    $agent = trim($row['agent']);

    $content = '';
    $ok = true;
    $id = substr(str_repeat(' ', FIRST_COL_LENGTH) . $row['idAgents'], -(FIRST_COL_LENGTH - 2));
    $matches = array();

    $detectionStartTime = microtime(true);

    /***************************************************************************
     * Wurfl - PHP 5.3 port
     */

    $modules[11]->startTimer();

    $device = $modules[11]->detect($agent);

    $detectionWurflTime = $modules[11]->endTimer();

    /***************************************************************************
     * Wurfl - PHP 5.3 port - end
     */

    /***************************************************************************
     * Wurfl - PHP-API 5.2 original
     */

    $modules[7]->startTimer();

    $deviceOrig = $modules[7]->detect($agent);

    $detectionWurflOrigTime = $modules[7]->endTimer();

    storeProperties($deviceOrig);

    /***************************************************************************
     * Wurfl - PHP-API 5.2 original - end
     */

    /**
     * Auswertung
     */

    $detectionTime = microtime(true) - $detectionStartTime;

    $oldMemery = $actualMemory;
    $actualMemory = memory_get_usage(true);

    if ($device !== null) {
        $vollBrowser = $device->getVirtualCapability('advertised_browser') . ' ' . $device->getVirtualCapability('advertised_browser_version');
    } else {
        $vollBrowser = '';
    }
    $startString = '#count#x found|' . str_repeat(' ', count($targets)) . '|';
    $deviceOk = formatMessage($content, $matches,     'Browser',               $startString, ($device === null ? null : $device->getVirtualCapability('advertised_browser')),    array($targets[7] => ($deviceOrig === null ? null : $deviceOrig->getVirtualCapability('advertised_browser')),    /**$targets[8] => ($deviceTera === null ? null : $deviceTera->getVirtualCapability('advertised_browser'))/**/),    $vollBrowser) && $ok;
    $ok = $deviceOk && $ok;
    unset($deviceOk);

    $startString = '#plus# + detected|' . str_repeat(' ', count($targets)) . '|';
    $ok = formatMessage($content, $matches,     'Engine',                      $startString, null,                                                                               array($targets[7] => null,                                                                                       /**$targets[8] => null/**/),                                                                                       $vollBrowser) && $ok;

    $startString = '#percent1# % +|' . str_repeat(' ', count($targets)) . '|';
    $osOk = formatMessage($content, $matches,     'OS',                        $startString, ($device === null ? null : $device->getVirtualCapability('advertised_device_os')),  array($targets[7] => ($deviceOrig === null ? null : $deviceOrig->getVirtualCapability('advertised_device_os')),  /**$targets[8] => ($deviceTera === null ? null : $deviceTera->getVirtualCapability('advertised_device_os'))/**/),  $vollBrowser);
    $ok = $osOk && $ok;

    $startString = '#minus# - detected|' . str_repeat(' ', count($targets)) . '|';
    $deviceOk = formatMessage($content, $matches,     'Device',                $startString, ($device === null ? null : $device->getCapability('model_name')),                   array($targets[7] => ($deviceOrig === null ? null : $deviceOrig->getCapability('model_name')),                   /**$targets[8] => ($deviceTera === null ? null : $deviceTera->getCapability('model_name'))/**/),                   $vollBrowser) && $ok;
    $ok = $deviceOk && $ok;
    unset($deviceOk);

    $startString = '#percent2# % -|' . str_repeat(' ', count($targets)) . '|';
    $ok = formatMessage($content, $matches,     'Desktop',                     $startString, ($device === null ? null : $device->getVirtualCapability('is_full_desktop')),       array($targets[7] => ($deviceOrig === null ? null : $deviceOrig->getVirtualCapability('is_full_desktop')),       /**$targets[8] => ($deviceTera === null ? null : $deviceTera->getVirtualCapability('is_full_desktop'))/**/),       $vollBrowser) && $ok;

    $startString = '#soso# : detected|' . str_repeat(' ', count($targets)) . '|';
    $ok = formatMessage($content, $matches,     'TV',                          $startString, ($device === null ? null : $device->getCapability('is_smarttv')),                   array($targets[7] => ($deviceOrig === null ? null : $deviceOrig->getCapability('is_smarttv')),                   /**$targets[8] => ($deviceTera === null ? null : $deviceTera->getCapability('is_smarttv'))/**/),                   $vollBrowser) && $ok;

    $startString = '#percent3# % :|' . str_repeat(' ', count($targets)) . '|';
    $ok = formatMessage($content, $matches,     'Mobile',                      $startString, ($device === null ? null : $device->getVirtualCapability('is_mobile')),             array($targets[7] => ($deviceOrig === null ? null : $deviceOrig->getVirtualCapability('is_mobile')),             /**$targets[8] => ($deviceTera === null ? null : $deviceTera->getVirtualCapability('is_mobile'))/**/),             $vollBrowser) && $ok;

    $startString = str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(' ', count($targets)) . '|';
    $okKey = formatMessage($content, $matches,     'Wurfl-Key',                   $startString, ($device === null ? null : $device->id),                                            array($targets[7] => ($deviceOrig === null ? null : $deviceOrig->id),                                            /**$targets[8] => ($deviceTera === null ? null : $deviceTera->id)/**/),                                            $vollBrowser);

    $ok = $okKey && $ok;
    $ok = formatMessage($content, $matches,     'Tablet',                      $startString, ($device === null ? null : $device->getCapability('is_tablet')),                    array($targets[7] => ($deviceOrig === null ? null : $deviceOrig->getCapability('is_tablet')),                    /**$targets[8] => ($deviceTera === null ? null : $deviceTera->getCapability('is_tablet'))/**/),                    $vollBrowser) && $ok;
    $ok = formatMessage($content, $matches,     'Bot',                         $startString, ($device === null ? null : $device->getVirtualCapability('is_robot')),              array($targets[7] => ($deviceOrig === null ? null : $deviceOrig->getVirtualCapability('is_robot')),              /**$targets[8] => ($deviceTera === null ? null : $deviceTera->getVirtualCapability('is_robot'))/**/),              $vollBrowser) && $ok;
    $ok = formatMessage($content, $matches,     'Console',                     $startString, ($device === null ? null : $device->getCapability('is_console')),                   array($targets[7] => ($deviceOrig === null ? null : $deviceOrig->getCapability('is_console')),                   /**$targets[8] => ($deviceTera === null ? null : $deviceTera->getCapability('is_console'))/**/),                   $vollBrowser) && $ok;
    $ok = formatMessage($content, $matches,     'Transcoder',                  $startString, ($device === null ? null : $device->getCapability('is_transcoder')),                array($targets[7] => ($deviceOrig === null ? null : $deviceOrig->getCapability('is_transcoder')),                /**$targets[8] => ($deviceTera === null ? null : $deviceTera->getCapability('is_transcoder'))/**/),                $vollBrowser) && $ok;

    $ok = formatMessage($content, $matches,     'Device-Hersteller',           $startString, ($device === null ? null : $device->getCapability('manufacturer_name')),            array($targets[7] => ($deviceOrig === null ? null : $deviceOrig->getCapability('manufacturer_name')),            /**$targets[8] => ($deviceTera === null ? null : $deviceTera->getCapability('manufacturer_name'))/**/),            $vollBrowser) && $ok;

    $allCapabilities = ($device === null ? array() : $device->getAllCapabilities());

    foreach (array_keys($allCapabilities) as $key) {
        $deviceContent = '';
        $returnMatches = array();
        $deviceOk      = formatMessage($deviceContent, $returnMatches, 'Capability: ' . $key, $startString, ($device === null ? null : $device->getCapability($key)), array($targets[7] => ($deviceOrig === null ? null : $deviceOrig->getCapability($key)), /**$targets[8] => ($deviceTera === null ? null : $deviceTera->getCapability($key))/**/), $vollBrowser);

        if (!$deviceOk) {
            $content .= $deviceContent;
            $matches  = $matches + $returnMatches;
        }

        $ok = $ok && $deviceOk;
        unset($deviceOk, $returnMatches, $deviceContent);
    }

    $allCapabilities = ($device === null ? array() : $device->getAllVirtualCapabilities());

    foreach (array_keys($allCapabilities) as $key) {
        $deviceContent = '';
        $returnMatches = array();
        $deviceOk      = formatMessage($deviceContent, $returnMatches, 'VirtualCapability: ' . $key, $startString, ($device === null ? null : $device->getVirtualCapability($key)), array($targets[7] => ($deviceOrig === null ? null : $deviceOrig->getVirtualCapability($key)), /**$targets[8] => ($deviceTera === null ? null : $deviceTera->getVirtualCapability($key))/**/), $vollBrowser);

        if (!$deviceOk) {
            $content .= $deviceContent;
            $matches  = $matches + $returnMatches;
        }

        $ok = $ok && $deviceOk;

        unset($deviceOk, $returnMatches, $deviceContent);
    }

    if (in_array('-', $matches) || !$ok) {
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
            str_pad($sosofound, FIRST_COL_LENGTH - 11, ' '),
            str_pad(number_format((100 * $okfound / $i), 9, ',', '.'), FIRST_COL_LENGTH - 4, ' ', STR_PAD_LEFT),
            str_pad(number_format((100 * $nokfound / $i), 9, ',', '.'), FIRST_COL_LENGTH - 4, ' ', STR_PAD_LEFT),
            str_pad(number_format((100 * $sosofound / $i), 9, ',', '.'), FIRST_COL_LENGTH - 4, ' ', STR_PAD_LEFT),
        ),
        $content
    );
    if (!$ok
        // || ($i <= 5)
    ) {
        echo '-';
        echo "\n";
        echo str_repeat('-', FIRST_COL_LENGTH) . '+' . str_repeat('-', count($targets)) . '+' . str_repeat('-', $aLength) . "\n";

        reset($targets);
        echo $colorStart . str_pad($i, FIRST_COL_LENGTH, ' ', STR_PAD_LEFT) . '|' . str_repeat('-', count($targets)) . '|' . str_repeat('-', SECOND_COL_LENGTH) . '|' . str_repeat('-', COL_LENGTH) . '|';
        foreach ($targets as $target) {
            echo str_repeat('-', COL_LENGTH) . '|';
        }
        echo $colorEnd . "\n";

        reset($targets);
        echo $colorStart . 'ID' . $id . '|' . str_repeat(' ', count($targets)) . '| ' .(strlen($agent) > ($aLength - 1) ?($aLength > 0 ? substr($agent, 0, $aLength - 4) . '...' : '') : $agent) . $colorEnd . "\n";
        echo $colorStart . str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(' ', count($targets)) . '| found last   : ' . $row['created'] . $colorEnd . "\n";
        echo $colorStart . str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(' ', count($targets)) . '| found in File: ' . $row['file'] . $colorEnd . "\n";

        echo $colorStart . str_pad($i . '/' . $count, FIRST_COL_LENGTH, ' ', STR_PAD_LEFT) . '|' . str_repeat(' ', count($targets)) . '|' . str_repeat(' ', SECOND_COL_LENGTH) . '|' . str_pad($target, COL_LENGTH, ' ', STR_PAD_RIGHT) . '|';
        $tagetTitles = array($targets[7]);
        foreach ($tagetTitles as $targetX) {
            echo str_pad($targetX, COL_LENGTH, ' ', STR_PAD_RIGHT) . '|';
        }
        echo $colorEnd . "\n";

        reset($targets);
        echo $colorStart . str_pad($i, FIRST_COL_LENGTH, ' ', STR_PAD_LEFT) . '|' . str_pad(($ok ? '+' : '-'), count($targets), ' ') . '|' . str_repeat('-', SECOND_COL_LENGTH) . '|' . str_repeat('-', COL_LENGTH) . '|';

        foreach ($targets as $targetX) {
            echo str_repeat('-', COL_LENGTH) . '|';
        }
        echo $colorEnd . "\n";

        echo $content;

        reset($targets);
        echo $colorStart . str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(' ', count($targets)) . '|' . str_repeat('-', SECOND_COL_LENGTH) . '|' . str_repeat('-', COL_LENGTH) . '|';
        foreach ($targets as $targetX) {
            echo str_repeat('-', COL_LENGTH) . '|';
        }
        echo $colorEnd . "\n";
        echo $colorStart . str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(' ', count($targets)) . '|' . $colorEnd . "\n";

        $fullTime = microtime(true) - $startTime;

        echo $startString . 'Time:   Detection (' . $target . ')' . str_repeat(' ', 40 - strlen($target)) . ':' . number_format($detectionWurflTime, 10, ',', '.') . ' Sek.' . "\n";
        echo $startString . '        Detection (' . $targets[7] . ')' . str_repeat(' ', 40 - strlen($targets[7])) . ':' . number_format($detectionWurflOrigTime, 10, ',', '.') . ' Sek.' . "\n";
        echo $startString . '        Detection (complete)' . str_repeat(' ', 40 - strlen('complete')) . ':' . number_format($detectionTime, 10, ',', '.') . ' Sek.' . "\n";
        echo $startString . '        Complete                                            :' . number_format($fullTime, 10, ',', '.') . ' Sek.' . "\n";
        $totalTime = microtime(true) - START_TIME;
        echo $startString . '        Absolute TOTAL                   :' . formatTime(microtime(true) - START_TIME) . "\n";
        echo $startString . 'Memory: ' . number_format($actualMemory, 0, ',', '.') . ' Bytes [Diff:' . number_format($actualMemory - $oldMemery, 0, ',', '.') . ' Bytes]' . "\n";
        echo "\n";
    } else {
        echo '.';
    }


    if (($i % 100) == 0) {
        echo "  $okfound OK, $nokfound NOK, $sosofound others\n";
    }

    ++$i;

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
        $matches,
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
        $detectionTime,
        $oldMemery,
        $vollBrowser,
        $allCapabilities
    );
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

function formatMessage(&$content, &$matches, $property, $start, $reality, array $targets, $device)
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

    $tooLong   = false;
    $strTarget = '(NULL)';

    foreach ($targets as $targetName => $target) {
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

        if (strtolower($target) === strtolower($reality)) {
            $r  = '+';
            $r1 = '+';
        } elseif (((null === $reality) || ('' === $reality) || ('' === $strReality)) && ((null === $target) || ('' === $target))) {
            $r  = ' ';
            $r1 = '?';
        } elseif ((null === $target) || ('' === $target) || ('' === $strTarget)) {
            $r  = ' ';
            $r1 = '%';
        } else {
            $mismatch   = true;
            $startcolor = COLOR_START_RED;
            $passed     = false;
            $r1         = '-';
            $r          = '-';

            /*
            if ((strlen($strTarget) > strlen($strReality))
                && (0 < strlen($strReality))
                && (0 === strpos($strTarget, $strReality))
            ) {
                $r  = ' ';
                $r1 = '<';
            } elseif ((strlen($strTarget) < strlen($strReality))
                && (0 < strlen($strTarget))
                && (0 === strpos($strReality, $strTarget))
            ) {
                $r  = ' ';
                $r1 = '>';
            } elseif (isset($allErrors[$targetName][$device][$property])) {
                $r  = ':';
                $r1 = ':';
            }
            /**/
        }

        $testresult .= $r;
        $matches[]   = $r;

        if (!isset($allErrors[$targetName][$device][$property])
            && $mismatch
        ) {
            $allErrors[$targetName][$device][$property] = $reality;
        }

        $prefix  = $r1;
        $tooLong = $tooLong || (strlen($strTarget) > COL_LENGTH);

        $detectionMessage[$targetName] = str_pad($prefix . $strTarget, COL_LENGTH, ' ') . '|';
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

/**
 * @var WURFL_CustomDevice $deviceOrig
 */
function storeProperties($deviceOrig = null)
{
    if (null !== $deviceOrig && !file_exists('data/browser/' . $deviceOrig->id . '.php')) {
        $props = $deviceOrig->getAllCapabilities();

        $content   = "<?php\nreturn array(\n";
        $keylength = 0;

        foreach ($props as $key => $value) {
            $keylength = max($keylength, strlen($key) + 2);
        }

        foreach ($props as $key => $value) {
            if ('true' === $value) {
                $valueOut = 'true';
            } elseif ('false' === $value) {
                $valueOut = 'false';
            } elseif ('0' === $value) {
                $valueOut = '0';
            } else {
                $valueOut = '\'' . $value . '\'';
            }

            $key = str_pad('\'' . $key  . '\'', $keylength + 1, ' ', STR_PAD_RIGHT);

            $content .= '    ' . $key . '=> ' . $valueOut . ",\n";
        }

        $content .= ");\n";

        file_put_contents('data/browser/' . $deviceOrig->id . '.php', $content);
    }
}
