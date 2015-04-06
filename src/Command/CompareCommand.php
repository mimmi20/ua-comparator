<?php
/**
 * Copyright (c) 2015, Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @category  UaComparator
 * @package   UaComparator
 * @author    Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 * @copyright 2015 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 * @link      https://github.com/mimmi20/ua-comparator
 */

namespace UaComparator\Command;

use Browscap\Helper\LoggerHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use BrowscapPHP\Helper\IniLoader;
use UaComparator\Helper\MessageFormatter;
use UaComparator\Helper\TimeFormatter;
use UaComparator\Module\BrowserDetectorModule;
use UaComparator\Module\ModuleCollection;
use UaComparator\Module\Wurfl;
use UaComparator\Module\WurflOld;
use WurflCache\Adapter\File;
use Browscap\Generator\BuildFullFileOnlyGenerator;
use UaComparator\Module\Browscap;
use UaComparator\Module\CrossJoin;
use UaComparator\Module\UaParser;
use WurflCache\Adapter\Memory;
use BrowserDetector\Detector\Version;

define('ROW_LENGTH', 397);
define('COL_LENGTH', 50);
define('FIRST_COL_LENGTH', 20);

define('START_TIME', microtime(true));

define('COLOR_END', "\x1b[0m");
define('COLOR_START_RED', "\x1b[37;41m");
define('COLOR_START_YELLOW', "\x1b[30;43m");
define('COLOR_START_GREEN', "\x1b[30;42m");

/**
 * Class CompareCommand
 *
 * @category   UaComparator
 * @package    Command
 * @author     Thomas Müller <t_mueller_stolzenhain@yahoo.de>
 */
class CompareCommand extends Command
{
    /**
     * Configures the current command.
     */
    protected function configure()
    {
        $defaultModules = array(
            'BrowserDetector',
            'Wurfl',
            'Browscap',
            'CrossJoin',
            'Piwik',
            'UaParser',
            'Wurfl52',
            /*'UASParser',*/
        );

        $this
            ->setName('compare')
            ->setDescription('compares different useragent parsers')
            ->addOption(
                'modules',
                null,
                InputArgument::REQUIRED | InputOption::VALUE_IS_ARRAY,
                'The Modules to compare',
                $defaultModules
            )
//            ->addArgument('version', InputArgument::REQUIRED, 'Version number to apply')
//            ->addOption('resources', null, InputOption::VALUE_REQUIRED, 'Where the resource files are located', $defaultResourceFolder)
//            ->addOption('debug', null, InputOption::VALUE_NONE, 'Should the debug mode entered?')
        ;
    }

    /**
     * Executes the current command.
     *
     * This method is not abstract because you can use this class
     * as a concrete class. In this case, instead of defining the
     * execute() method, you set the code to execute by passing
     * a Closure to the setCode() method.
     *
     * @param \Symfony\Component\Console\Input\InputInterface  $input  An InputInterface instance
     * @param \Symfony\Component\Console\Output\OutputInterface $output An OutputInterface instance
     *
     * @return null|integer null or 0 if everything went fine, or an error code
     *
     * @throws \LogicException When this abstract method is not implemented
     * @see    setCode()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $loggerHelper = new LoggerHelper();
        $logger       = $loggerHelper->create();

        $logger->info('initializing App ...');

        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', 0);
        ini_set('max_input_time', 0);
        ini_set('display_errors', 1);
        ini_set('error_log', './error.log');
        error_reporting(E_ALL | E_DEPRECATED);

        date_default_timezone_set('Europe/Berlin');
        setlocale(LC_CTYPE, 'de_DE@euro', 'de_DE', 'de', 'ge');

        $logger->info('ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes');

        $modules    = $input->getOption('modules');
        $collection = new ModuleCollection();

        /*******************************************************************************
         * BrowserDetectorModule
         */

        if (in_array('BrowserDetector', $modules)) {
            $logger->info('initializing BrowserDetectorModule ...');

            $detectorModule = new BrowserDetectorModule($logger, new File(array('dir' => 'data/cache/browser/')));
            $detectorModule
                ->setId(0)
                ->setName('BrowserDetector')
            ;

            $collection->addModule($detectorModule);

            $logger->info('ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes');
        }

        /*******************************************************************************
         * checking full_php_browscap.ini
         */

        $iniFile = null;

        if (in_array('Browscap', $modules) || in_array('CrossJoin', $modules)) {
            $logger->info('checking full_php_browscap.ini ...');

            $buildNumber = (int) file_get_contents('vendor/browscap/browscap/BUILD_NUMBER');
            $iniFile     = 'data/browscap-ua-test-' . $buildNumber . '/full_php_browscap.ini';

            if (!file_exists($iniFile)) {
                $iniFile = null;
            }

            $logger->info('ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes');
        }

        /*******************************************************************************
         * Browscap-PHP
         */

        if (in_array('Browscap', $modules) && null !== $iniFile) {
            $logger->info('initializing Browscap-PHP ...');

            $browscapModule = new Browscap($logger, new File(array('dir' => 'data/cache/browscap/')));
            $browscapModule
                ->setId(9)
                ->setName('Browscap-PHP')
            ;

            $collection->addModule($browscapModule);

            $logger->info('ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes');
        }

        /*******************************************************************************
         * Crossjoin\Browscap
         */
        if (in_array('CrossJoin', $modules) && null !== $iniFile) {
            $logger->info('initializing Crossjoin\Browscap ...');

            $crossjoinModule = new CrossJoin($logger, new File(array('dir' => 'data/cache/crossjoin/')), $iniFile);
            $crossjoinModule
                ->setId(10)
                ->setName('Crossjoin\Browscap')
            ;

            $collection->addModule($crossjoinModule);

            $logger->info('ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes');
        }

        /*******************************************************************************
         * UAParser
         */
        if (in_array('UaParser', $modules)) {
            $logger->info('initializing UAParser ...');

            $uaparserModule = new UaParser($logger, new Memory());
            $uaparserModule
                ->setId(5)
                ->setName('UAParser')
            ;

            $collection->addModule($uaparserModule);

            $logger->info('ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes');
        }

        /*******************************************************************************
         * UASParser
         *
        if (in_array('UASParser', $modules)) {
            $logger->info('initializing UASParser ...');

            $uasparserModule = new UasParser($logger, new Memory());
            $uasparserModule
            ->setId(6)
            ->setName('UASParser')
            ;

            $collection->addModule($uasparserModule);

            $logger->info('ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes');
        }

        /*******************************************************************************
         * WURFL - PHP 5.3 port
         */
        if (in_array('Wurfl', $modules)) {
            $logger->info('initializing Wurfl API (PHP-API 5.3 port) ...');

            ini_set('max_input_time', '6000');
            $wurflModule = new Wurfl($logger, new File(array('dir' => 'data/cache/wurfl/')), 'data/wurfl-config.xml');
            $wurflModule
                ->setId(11)
                ->setName('WURFL API (PHP-API 5.3)')
            ;

            $collection->addModule($wurflModule);

            $logger->info('ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes');
        }

        /*******************************************************************************
         * WURFL - PHP 5.2 original
         */
        if (in_array('Wurfl52', $modules)) {
            $logger->info('initializing Wurfl API (PHP-API 5.2 original) ...');

            $oldWurflModule = new WurflOld($logger, new File(array('dir' => 'data/cache/wurfl_old/')), 'data/wurfl-config.xml');
            $oldWurflModule
                ->setId(7)
                ->setName('WURFL API (PHP-API 5.2 original)')
            ;

            $collection->addModule($oldWurflModule);

            $logger->info('ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes');
        }

        /*******************************************************************************
         * Piwik Parser
         */
        if (in_array('Piwik', $modules)) {
            $logger->info('initializing Piwik Parser ...');

            $adapter     = new Memory();
            $piwikModule = new \UaComparator\Module\PiwikDetector($logger, $adapter);
            $piwikModule
                ->setId(12)
                ->setName('Piwik Parser')
            ;

            $collection->addModule($piwikModule);

            $logger->info('ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes');
        }

        $logger->info('initializing all Modules ...');

        $collection->init();

        $logger->info('ready ' . TimeFormatter::formatTime(microtime(true) - START_TIME) . ' -  ' . number_format(memory_get_usage(true), 0, ',', '.') . ' Bytes');

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

        $logger->info(str_repeat('+', FIRST_COL_LENGTH + $aLength + $collection->count() - 1 + 2));

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

        $logger->info('');

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

        $checks = array(
            'Browser'                                        => array(
                'key'         => array('getFullBrowser', array(true, Version::MAJORMINOR)),
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'Engine'                                         => array(
                'key'         => array('getFullEngine', array(Version::MAJORMINOR)),
                'startString' => '#plus# + detected|' . str_repeat(' ', $collection->count() - 1) . '|'
            ),
            'OS'                                             => array(
                'key'         => array('getFullPlatform', array(true, Version::MAJORMINOR)),
                'startString' => '#percent1# % +|' . str_repeat(' ', $collection->count() - 1) . '|'
            ),
            'Device'                                         => array(
                'key'         => array('getFullDevice', array(true)),
                'startString' => '#minus# - detected|' . str_repeat(' ', $collection->count() - 1) . '|'
            ),
            'Desktop'                                        => array(
                'key'         => array('isDesktop'),
                'startString' => '#percent2# % -|' . str_repeat(' ', $collection->count() - 1) . '|'
            ),
            'TV'                                             => array(
                'key'         => array('isTvDevice'),
                'startString' => '#soso# : detected|' . str_repeat(' ', $collection->count() - 1) . '|'
            ),
            'Mobile'                                         => array(
                'key'         => array('isMobileDevice'),
                'startString' => '#percent3# % :|' . str_repeat(' ', $collection->count() - 1) . '|'
            ),
            'Tablet'                                         => array(
                'key'         => array('isTablet'),
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'Bot'                                            => array(
                'key'         => array('isCrawler'),
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'Console'                                        => array(
                'key'         => array('isConsole'),
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'Transcoder'                                     => array(
                'key'         => 'is_transcoder',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'Syndication-Reader'                             => array(
                'key'         => 'is_syndication_reader',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'Device Typ'                                     => array(
                'key'         => 'device_type',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'Browser Typ'                                    => array(
                'key'         => 'browser_type',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'Device-Hersteller'                              => array(
                'key'         => 'manufacturer_name',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'Browser-Hersteller'                             => array(
                'key'         => 'mobile_browser_manufacturer',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'OS-Hersteller'                                  => array(
                'key'         => 'device_os_manufacturer',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'Engine-Hersteller'                              => array(
                'key'         => 'renderingengine_manufacturer',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'pointing_method'                                => array(
                'key'         => 'pointing_method',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'model_name'                                     => array(
                'key'         => 'model_name',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'manufacturer_name'                              => array(
                'key'         => 'manufacturer_name',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'brand_name'                                     => array(
                'key'         => 'brand_name',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'model_extra_info'                               => array(
                'key'         => 'model_extra_info',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'marketing_name'                                 => array(
                'key'         => 'marketing_name',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'has_qwerty_keyboard'                            => array(
                'key'         => 'has_qwerty_keyboard',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            // product info
            'can_skip_aligned_link_row'                      => array(
                'key'         => 'can_skip_aligned_link_row',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'device_claims_web_support'                      => array(
                'key'         => 'device_claims_web_support',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'can_assign_phone_number'                        => array(
                'key'         => 'can_assign_phone_number',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'nokia_feature_pack'                             => array(
                'key'         => 'nokia_feature_pack',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'nokia_series'                                   => array(
                'key'         => 'nokia_series',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'nokia_edition'                                  => array(
                'key'         => 'nokia_edition',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'ununiqueness_handler'                           => array(
                'key'         => 'ununiqueness_handler',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'uaprof'                                         => array(
                'key'         => 'uaprof',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'uaprof2'                                        => array(
                'key'         => 'uaprof2',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'uaprof3'                                        => array(
                'key'         => 'uaprof3',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'unique'                                         => array(
                'key'         => 'unique',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            // display
            'physical_screen_width'                          => array(
                'key'         => 'physical_screen_width',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'physical_screen_height'                         => array(
                'key'         => 'physical_screen_height',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'columns'                                        => array(
                'key'         => 'columns',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'rows'                                           => array(
                'key'         => 'rows',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'max_image_width'                                => array(
                'key'         => 'max_image_width',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'max_image_height'                               => array(
                'key'         => 'max_image_height',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'resolution_width'                               => array(
                'key'         => 'resolution_width',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'resolution_height'                              => array(
                'key'         => 'resolution_height',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'dual_orientation'                               => array(
                'key'         => 'dual_orientation',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'colors'                                         => array(
                'key'         => 'colors',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            // markup
            'utf8_support'                                   => array(
                'key'         => 'utf8_support',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'multipart_support'                              => array(
                'key'         => 'multipart_support',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'supports_background_sounds'                     => array(
                'key'         => 'supports_background_sounds',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'supports_vb_script'                             => array(
                'key'         => 'supports_vb_script',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'supports_java_applets'                          => array(
                'key'         => 'supports_java_applets',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'supports_activex_controls'                      => array(
                'key'         => 'supports_activex_controls',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'preferred_markup'                               => array(
                'key'         => 'preferred_markup',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'html_web_3_2'                                   => array(
                'key'         => 'html_web_3_2',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'html_web_4_0'                                   => array(
                'key'         => 'html_web_4_0',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'html_wi_oma_xhtmlmp_1_0'                        => array(
                'key'         => 'html_wi_oma_xhtmlmp_1_0',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'wml_1_1'                                        => array(
                'key'         => 'wml_1_1',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'wml_1_2'                                        => array(
                'key'         => 'wml_1_2',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'wml_1_3'                                        => array(
                'key'         => 'wml_1_3',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_support_level'                            => array(
                'key'         => 'xhtml_support_level',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'html_wi_imode_html_1'                           => array(
                'key'         => 'html_wi_imode_html_1',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'html_wi_imode_html_2'                           => array(
                'key'         => 'html_wi_imode_html_2',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'html_wi_imode_html_3'                           => array(
                'key'         => 'html_wi_imode_html_3',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'html_wi_imode_html_4'                           => array(
                'key'         => 'html_wi_imode_html_4',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'html_wi_imode_html_5'                           => array(
                'key'         => 'html_wi_imode_html_5',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'html_wi_imode_htmlx_1'                          => array(
                'key'         => 'html_wi_imode_htmlx_1',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'html_wi_imode_htmlx_1_1'                        => array(
                'key'         => 'html_wi_imode_htmlx_1_1',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'html_wi_w3_xhtmlbasic'                          => array(
                'key'         => 'html_wi_w3_xhtmlbasic',
                'startString' => str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'html_wi_imode_compact_generic'                  => array(
                'key'         => 'html_wi_imode_compact_generic',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(' ', $collection->count() - 1) . '|'
            ),
            'voicexml'                                       => array(
                'key'         => 'voicexml',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(' ', $collection->count() - 1) . '|'
            ),
            // chtml
            'chtml_table_support'                            => array(
                'key'         => 'chtml_table_support',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(' ', $collection->count() - 1) . '|'
            ),
            'imode_region'                                   => array(
                'key'         => 'imode_region',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(' ', $collection->count() - 1) . '|'
            ),
            'chtml_can_display_images_and_text_on_same_line' => array(
                'key'         => 'chtml_can_display_images_and_text_on_same_line',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'chtml_displays_image_in_center'                 => array(
                'key'         => 'chtml_displays_image_in_center',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'chtml_make_phone_call_string'                   => array(
                'key'         => 'chtml_make_phone_call_string',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'emoji'                                          => array(
                'key'         => 'emoji',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            // xhtml
            'xhtml_select_as_radiobutton'                    => array(
                'key'         => 'xhtml_select_as_radiobutton',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_avoid_accesskeys'                         => array(
                'key'         => 'xhtml_avoid_accesskeys',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_select_as_dropdown'                       => array(
                'key'         => 'xhtml_select_as_dropdown',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_supports_iframe'                          => array(
                'key'         => 'xhtml_supports_iframe',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_supports_forms_in_table'                  => array(
                'key'         => 'xhtml_supports_forms_in_table',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtmlmp_preferred_mime_type'                    => array(
                'key'         => 'xhtmlmp_preferred_mime_type',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_select_as_popup'                          => array(
                'key'         => 'xhtml_select_as_popup',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_honors_bgcolor'                           => array(
                'key'         => 'xhtml_honors_bgcolor',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_file_upload'                              => array(
                'key'         => 'xhtml_file_upload',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_preferred_charset'                        => array(
                'key'         => 'xhtml_preferred_charset',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_supports_css_cell_table_coloring'         => array(
                'key'         => 'xhtml_supports_css_cell_table_coloring',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_autoexpand_select'                        => array(
                'key'         => 'xhtml_autoexpand_select',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'accept_third_party_cookie'                      => array(
                'key'         => 'accept_third_party_cookie',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_make_phone_call_string'                   => array(
                'key'         => 'xhtml_make_phone_call_string',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_allows_disabled_form_elements'            => array(
                'key'         => 'xhtml_allows_disabled_form_elements',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_supports_invisible_text'                  => array(
                'key'         => 'xhtml_supports_invisible_text',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'cookie_support'                                 => array(
                'key'         => 'cookie_support',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_send_mms_string'                          => array(
                'key'         => 'xhtml_send_mms_string',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_table_support'                            => array(
                'key'         => 'xhtml_table_support',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_display_accesskey'                        => array(
                'key'         => 'xhtml_display_accesskey',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_can_embed_video'                          => array(
                'key'         => 'xhtml_can_embed_video',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_supports_monospace_font'                  => array(
                'key'         => 'xhtml_supports_monospace_font',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_supports_inline_input'                    => array(
                'key'         => 'xhtml_supports_inline_input',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_document_title_support'                   => array(
                'key'         => 'xhtml_document_title_support',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_support_wml2_namespace'                   => array(
                'key'         => 'xhtml_support_wml2_namespace',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_readable_background_color1'               => array(
                'key'         => 'xhtml_readable_background_color1',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_format_as_attribute'                      => array(
                'key'         => 'xhtml_format_as_attribute',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_supports_table_for_layout'                => array(
                'key'         => 'xhtml_supports_table_for_layout',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_readable_background_color2'               => array(
                'key'         => 'xhtml_readable_background_color2',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_send_sms_string'                          => array(
                'key'         => 'xhtml_send_sms_string',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_format_as_css_property'                   => array(
                'key'         => 'xhtml_format_as_css_property',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'opwv_xhtml_extensions_support'                  => array(
                'key'         => 'opwv_xhtml_extensions_support',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_marquee_as_css_property'                  => array(
                'key'         => 'xhtml_marquee_as_css_property',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'xhtml_nowrap_mode'                              => array(
                'key'         => 'xhtml_nowrap_mode',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            // image format
            'jpg'                                            => array(
                'key'         => 'jpg',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'gif'                                            => array(
                'key'         => 'gif',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'bmp'                                            => array(
                'key'         => 'bmp',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'wbmp'                                           => array(
                'key'         => 'wbmp',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'gif_animated'                                   => array(
                'key'         => 'gif_animated',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'png'                                            => array(
                'key'         => 'png',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'greyscale'                                      => array(
                'key'         => 'greyscale',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'transparent_png_index'                          => array(
                'key'         => 'transparent_png_index',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'epoc_bmp'                                       => array(
                'key'         => 'epoc_bmp',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'svgt_1_1_plus'                                  => array(
                'key'         => 'svgt_1_1_plus',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'svgt_1_1'                                       => array(
                'key'         => 'svgt_1_1',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'transparent_png_alpha'                          => array(
                'key'         => 'transparent_png_alpha',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'tiff'                                           => array(
                'key'         => 'tiff',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            // security
            'https_support'                                  => array(
                'key'         => 'https_support',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            // storage
            'max_url_length_bookmark'                        => array(
                'key'         => 'max_url_length_bookmark',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'max_url_length_cached_page'                     => array(
                'key'         => 'max_url_length_cached_page',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'max_url_length_in_requests'                     => array(
                'key'         => 'max_url_length_in_requests',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'max_url_length_homepage'                        => array(
                'key'         => 'max_url_length_homepage',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            // ajax
            'ajax_support_getelementbyid'                    => array(
                'key'         => 'ajax_support_getelementbyid',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'ajax_xhr_type'                                  => array(
                'key'         => 'ajax_xhr_type',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'ajax_support_event_listener'                    => array(
                'key'         => 'ajax_support_event_listener',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'ajax_support_javascript'                        => array(
                'key'         => 'ajax_support_javascript',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'ajax_manipulate_dom'                            => array(
                'key'         => 'ajax_manipulate_dom',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'ajax_support_inner_html'                        => array(
                'key'         => 'ajax_support_inner_html',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'ajax_manipulate_css'                            => array(
                'key'         => 'ajax_manipulate_css',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'ajax_support_events'                            => array(
                'key'         => 'ajax_support_events',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'ajax_preferred_geoloc_api'                      => array(
                'key'         => 'ajax_preferred_geoloc_api',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            // pdf
            'pdf_support'                                    => array(
                'key'         => 'pdf_support',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            // third_party
            'jqm_grade'                                      => array(
                'key'         => 'jqm_grade',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'is_sencha_touch_ok'                             => array(
                'key'         => 'is_sencha_touch_ok',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            // html
            'image_inlining'                                 => array(
                'key'         => 'image_inlining',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'canvas_support'                                 => array(
                'key'         => 'canvas_support',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'viewport_width'                                 => array(
                'key'         => 'viewport_width',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'html_preferred_dtd'                             => array(
                'key'         => 'html_preferred_dtd',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'viewport_supported'                             => array(
                'key'         => 'viewport_supported',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'viewport_minimum_scale'                         => array(
                'key'         => 'viewport_minimum_scale',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'viewport_initial_scale'                         => array(
                'key'         => 'viewport_initial_scale',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'mobileoptimized'                                => array(
                'key'         => 'mobileoptimized',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'viewport_maximum_scale'                         => array(
                'key'         => 'viewport_maximum_scale',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'viewport_userscalable'                          => array(
                'key'         => 'viewport_userscalable',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'handheldfriendly'                               => array(
                'key'         => 'handheldfriendly',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            // css
            'css_spriting'                                   => array(
                'key'         => 'css_spriting',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'css_gradient'                                   => array(
                'key'         => 'css_gradient',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'css_gradient_linear'                            => array(
                'key'         => 'css_gradient_linear',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'css_border_image'                               => array(
                'key'         => 'css_border_image',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'css_rounded_corners'                            => array(
                'key'         => 'css_rounded_corners',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'css_supports_width_as_percentage'               => array(
                'key'         => 'css_supports_width_as_percentage',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            // bugs
            'empty_option_value_support'                     => array(
                'key'         => 'empty_option_value_support',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'basic_authentication_support'                   => array(
                'key'         => 'basic_authentication_support',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            'post_method_support'                            => array(
                'key'         => 'post_method_support',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            // rss
            'rss_support'                                    => array(
                'key'         => 'rss_support',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            // sms
            'sms_enabled'                                    => array(
                'key'         => 'sms_enabled',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
            // chips
            'nfc_support'                                    => array(
                'key'         => 'nfc_support',
                'startString' => str_repeat(
                        ' ',
                        FIRST_COL_LENGTH
                    ) . '|' . str_repeat(
                        ' ',
                        $collection->count() - 1
                    ) . '|'
            ),
        );




        /*******************************************************************************
         * Loop
         */
        $lineHandler = new \UaComparator\Helper\LineHandler();

        foreach ($files as $path) {
            /** @var $path \SplFileInfo */
            $loader->setLocalFile($path);
            $internalLoader = $loader->getLoader();

            if ($internalLoader->isSupportingLoadingLines()) {
                if (!$internalLoader->init($path)) {
                    $logger->info('Skipping empty file "' . $path->getPathname() . '"');
                    continue;
                }

                while ($internalLoader->isValid()) {
                    try {
                        $lineHandler->handleLine($internalLoader->getLine(), $collection, $messageFormatter, $i, $checks);
                        continue;
                    } catch (\Exception $e) {
                        if (1 === $e->getCode()) {
                            $nokfound++;
                        } elseif (2 === $e->getCode()) {
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

                        $logger->info($content);
                    }

                    $i++;
                }

                $internalLoader->close();
                $i--;
            } else {
                $lines = file($path);

                if (empty($lines)) {
                    $logger->info('Skipping empty file "' . $path->getPathname() . '"');
                    continue;
                }

                foreach ($lines as $line) {
                    try {
                        $lineHandler->handleLine($line, $collection, $messageFormatter, $i, $checks);
                        continue;
                    } catch (\Exception $e) {
                        if (1 === $e->getCode()) {
                            $nokfound++;
                        } elseif (2 === $e->getCode()) {
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

                        $logger->info($content);
                    }

                    $i++;
                }
                $i--;
            }
        }

        $logger->info('');
        $logger->info(str_repeat('-', FIRST_COL_LENGTH) . '+' . str_repeat('-', $collection->count() - 1) . '+' . str_repeat('-', $aLength));

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


        $logger->info(substr(str_repeat(' ', FIRST_COL_LENGTH) . $i . '/' . $count, -1 * FIRST_COL_LENGTH) . '|' . "\n" . $content);
    }
}
