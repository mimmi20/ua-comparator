<?php
/**
 * This file is part of the ua-comparator package.
 *
 * Copyright (c) 2015-2017, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);
namespace UaComparator\Helper;

/**
 * Class Check
 *
 * @author  Thomas Mueller <mimmi20@live.de>
 */
class Check
{
    const MINIMUM = 'minimum';
    const MEDIUM  = 'medium';

    /**
     * Executes the current command.
     *
     * This method is not abstract because you can use this class
     * as a concrete class. In this case, instead of defining the
     * execute() method, you set the code to execute by passing
     * a Closure to the setCode() method.
     *
     * @return array
     */
    public function getChecks(): array
    {
        $checks = [
            'Browser' => [
                'key' => 'mobile_browser',
            ],
            'Browser Version' => [
                'key' => 'mobile_browser_version',
            ],
            'Browser Modus' => [
                'key' => 'mobile_browser_modus',
            ],
            'Browser Bits' => [
                'key' => 'mobile_browser_bits',
            ],
            'Browser Typ' => [
                'key' => 'browser_type',
            ],
            'Browser Hersteller' => [
                'key' => 'mobile_browser_manufacturer',
            ],
            'Engine' => [
                'key' => 'renderingengine_name',
            ],
            'Engine Version' => [
                'key' => 'renderingengine_version',
            ],
            'Engine Hersteller' => [
                'key' => 'renderingengine_manufacturer',
            ],
            'OS' => [
                'key' => 'device_os',
            ],
            'OS Version' => [
                'key' => 'device_os_version',
            ],
            'OS Bits' => [
                'key' => 'device_os_bits',
            ],
            'OS Hersteller' => [
                'key' => 'device_os_manufacturer',
            ],
            'Device Brand Name' => [
                'key' => 'brand_name',
            ],
            'Device Marketing Name' => [
                'key' => 'marketing_name',
            ],
            'Device Model Name' => [
                'key' => 'model_name',
            ],
            'Device Hersteller' => [
                'key' => 'manufacturer_name',
            ],
            'Device Typ' => [
                'key' => 'device_type',
            ],
//            'Desktop'               => [
//                'key'         => ['isDesktop'],
//            ],
//            'TV'                    => [
//                'key'         => ['isTvDevice'],
//            ],
//            'Mobile'                => [
//                'key'         => ['isMobileDevice'],
//            ],
//            'Tablet'                => [
//                'key'         => ['isTablet'],
//            ],
//            'Bot'                   => [
//                'key'         => ['isCrawler'],
//            ],
//            'Console'               => [
//                'key'         => ['isConsole'],
//            ],
//            'Transcoder'            => [
//                'key'         => 'is_transcoder',
//            ],
//            'Syndication-Reader'    => [
//                'key'         => 'is_syndication_reader',
//            ],
            'pointing_method' => [
                'key' => 'pointing_method',
            ],
            'has_qwerty_keyboard' => [
                'key' => 'has_qwerty_keyboard',
            ],
            // display
            'resolution_width' => [
                'key' => 'resolution_width',
            ],
            'resolution_height' => [
                'key' => 'resolution_height',
            ],
            'dual_orientation' => [
                'key' => 'dual_orientation',
            ],
            'colors' => [
                'key' => 'colors',
            ],
        ];

        return $checks;
    }
}
