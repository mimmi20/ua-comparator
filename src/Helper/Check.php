<?php

/**
 * This file is part of the mimmi20/ua-comparator package.
 *
 * Copyright (c) 2015-2025, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace UaComparator\Helper;

final class Check
{
    public const string MINIMUM = 'minimum';

    public const string MEDIUM = 'medium';

    /**
     * Executes the current command.
     *
     * This method is not abstract because you can use this class
     * as a concrete class. In this case, instead of defining the
     * {@see execute()} method, you set the code to execute by passing
     * a Closure to the setCode() method.
     *
     * @throws void
     */
    public function getChecks(): array
    {
        return [
            'Browser' => ['key' => 'mobile_browser'],
            'Browser Bits' => ['key' => 'mobile_browser_bits'],
            'Browser Hersteller' => ['key' => 'mobile_browser_manufacturer'],
            'Browser Modus' => ['key' => 'mobile_browser_modus'],
            'Browser Typ' => ['key' => 'browser_type'],
            'Browser Version' => ['key' => 'mobile_browser_version'],
            'colors' => ['key' => 'colors'],
            'Device Brand Name' => ['key' => 'brand_name'],
            'Device Hersteller' => ['key' => 'manufacturer_name'],
            'Device Marketing Name' => ['key' => 'marketing_name'],
            'Device Model Name' => ['key' => 'model_name'],
            'Device Typ' => ['key' => 'device_type'],
            'dual_orientation' => ['key' => 'dual_orientation'],
            'Engine' => ['key' => 'renderingengine_name'],
            'Engine Hersteller' => ['key' => 'renderingengine_manufacturer'],
            'Engine Version' => ['key' => 'renderingengine_version'],
            'has_qwerty_keyboard' => ['key' => 'has_qwerty_keyboard'],
            'OS' => ['key' => 'device_os'],
            'OS Bits' => ['key' => 'device_os_bits'],
            'OS Hersteller' => ['key' => 'device_os_manufacturer'],
            'OS Version' => ['key' => 'device_os_version'],
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
            'pointing_method' => ['key' => 'pointing_method'],
            'resolution_height' => ['key' => 'resolution_height'],
            // display
            'resolution_width' => ['key' => 'resolution_width'],
        ];
    }
}
