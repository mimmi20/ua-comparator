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

namespace UaComparator\Module\Mapper;

use BrowserDetector\Detector\Result;
use BrowserDetector\Detector\Version;
use UaComparator\Helper\InputMapper;

/**
 * Browscap.ini parsing class with caching and update capabilities
 *
 * @category  UaComparator
 * @package   UaComparator
 * @author    Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 * @copyright 2015 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 */
class Browscap implements MapperInterface
{
    /**
     * Gets the information about the browser by User Agent
     *
     * @param \stdClass $parserResult
     *
     * @return \BrowserDetector\Detector\Result the object containing the browsers details.
     */
    public function map(\stdClass $parserResult)
    {
        $result = new Result();
        $mapper = new InputMapper();

        $browserName    = $this->detectProperty($parserResult, 'browser');
        $browserVersion = $this->detectProperty(
            $parserResult, 'version', true, $browserName
        );

        $browserName    = $mapper->mapBrowserName(trim($browserName));
        $browserVersion = $mapper->mapBrowserVersion(
            trim($browserVersion), $browserName
        );

        $browserBits = $this->detectProperty(
            $parserResult, 'browser_bits', true, $browserName
        );

        $browserMaker = $this->detectProperty(
            $parserResult, 'browser_maker', true, $browserName
        );

        $result->setCapability('mobile_browser', $browserName);
        $result->setCapability('mobile_browser_version', $browserVersion);
        $result->setCapability('mobile_browser_bits', $browserBits);
        $result->setCapability(
            'mobile_browser_manufacturer',
            $mapper->mapBrowserMaker($browserMaker, $browserName)
        );

        if (!empty($parserResult->browser_type)) {
            $browserType = $parserResult->browser_type;
        } else {
            $browserType = null;
        }

        $result->setCapability('browser_type', $mapper->mapBrowserType($browserType, $browserName)->getName());

        if (!empty($parserResult->browser_modus) && 'unknown' !== $parserResult->browser_modus) {
            $browserModus = $parserResult->browser_modus;
        } else {
            $browserModus = null;
        }

        $result->setCapability('mobile_browser_modus', $browserModus);

        $platform = $this->detectProperty($parserResult, 'platform');

        $platformVersion = $this->detectProperty(
            $parserResult, 'platform_version', true, $platform
        );

        $platformVersion = $mapper->mapOsVersion(trim($platformVersion), trim($platform));
        $platform        = $mapper->mapOsName(trim($platform));

        $platformbits  = $this->detectProperty(
            $parserResult, 'platform_bits', true, $platform
        );
        $platformMaker = $this->detectProperty(
            $parserResult, 'platform_maker', true, $platform
        );

        $result->setCapability('device_os', $platform);
        $result->setCapability('device_os_version', $platformVersion);
        $result->setCapability('device_os_bits', $platformbits);
        $result->setCapability('device_os_manufacturer', $platformMaker);

        $deviceName = $this->detectProperty($parserResult, 'device_code_name');
        $deviceType = $this->detectProperty($parserResult, 'device_type');

        $result->setCapability('device_type', $mapper->mapDeviceType($deviceType));

        $deviceName = $mapper->mapDeviceName($deviceName);

        $deviceMaker = $this->detectProperty(
            $parserResult, 'device_maker', true, $deviceName
        );

        $deviceMarketingName = $this->detectProperty(
            $parserResult, 'device_name', true, $deviceName
        );

        $deviceBrandName = $this->detectProperty(
            $parserResult, 'device_brand_name', true, $deviceName
        );

        $devicePointingMethod = $this->detectProperty(
            $parserResult, 'device_pointing_method', true, $deviceName
        );

        $result->setCapability('model_name', $deviceName);
        $result->setCapability('marketing_name', $mapper->mapDeviceMarketingName($deviceMarketingName, $deviceName));
        $result->setCapability('brand_name', $mapper->mapDeviceBrandName($deviceBrandName, $deviceName));
        $result->setCapability('manufacturer_name', $mapper->mapDeviceMaker($deviceMaker, $deviceName));
        $result->setCapability('pointing_method', $devicePointingMethod);

        $engineName = $this->detectProperty($parserResult, 'renderingengine_name');

        if ('unknown' === $engineName || '' === $engineName) {
            $engineName = null;
        }

        $engineMaker = $this->detectProperty($parserResult, 'renderingengine_maker', true, $engineName);

        $version = new Version();
        $version->setMode(
            Version::COMPLETE | Version::IGNORE_MINOR_IF_EMPTY | Version::IGNORE_MICRO_IF_EMPTY
        );

        $engineVersion = $mapper->mapEngineVersion(
            $this->detectProperty($parserResult, 'renderingengine_version', true, $engineName)
        );

        $result->setCapability('renderingengine_name', $engineName);
        $result->setCapability('renderingengine_version', $engineVersion);
        $result->setCapability('renderingengine_manufacturer', $engineMaker);

        $result->setCapability('ux_full_desktop', $deviceType === 'Desktop');
        $result->setCapability('is_smarttv', $deviceType === 'TV Device');
        $result->setCapability('is_tablet', $deviceType === 'Tablet');

        if (!empty($parserResult->ismobiledevice)) {
            $result->setCapability(
                'is_wireless_device', $parserResult->ismobiledevice
            );
        }

        if (!empty($parserResult->istablet)) {
            $result->setCapability('is_tablet', $parserResult->istablet);
        } else {
            $result->setCapability('is_tablet', null);
        }
        $result->setCapability('is_bot', $parserResult->crawler);

        $result->setCapability(
            'is_syndication_reader', $parserResult->issyndicationreader
        );

        if (!empty($parserResult->frames)) {
            $framesSupport = $parserResult->frames;
        } else {
            $framesSupport = null;
        }

        $result->setCapability('xhtml_supports_frame', $mapper->mapFrameSupport($framesSupport));

        if (!empty($parserResult->iframes)) {
            $framesSupport = $parserResult->iframes;
        } else {
            $framesSupport = null;
        }

        $result->setCapability('xhtml_supports_iframe', $mapper->mapFrameSupport($framesSupport));

        if (!empty($parserResult->tables)) {
            $tablesSupport = $parserResult->tables;
        } else {
            $tablesSupport = null;
        }

        $result->setCapability('xhtml_table_support', $tablesSupport);

        if (!empty($parserResult->cookies)) {
            $cookieSupport = $parserResult->cookies;
        } else {
            $cookieSupport = null;
        }

        $result->setCapability('cookie_support', $cookieSupport);

        if (!empty($parserResult->backgroundsounds)) {
            $bgsoundSupport = $parserResult->backgroundsounds;
        } else {
            $bgsoundSupport = null;
        }

        $result->setCapability('supports_background_sounds', $bgsoundSupport);

        if (!empty($parserResult->vbscript)) {
            $vbSupport = $parserResult->vbscript;
        } else {
            $vbSupport = null;
        }

        $result->setCapability('supports_vb_script', $vbSupport);

        if (!empty($parserResult->javascript)) {
            $jsSupport = $parserResult->javascript;
        } else {
            $jsSupport = null;
        }

        $result->setCapability('ajax_support_javascript', $jsSupport);

        if (!empty($parserResult->javaapplets)) {
            $appletsSupport = $parserResult->javaapplets;
        } else {
            $appletsSupport = null;
        }

        $result->setCapability('supports_java_applets', $appletsSupport);

        if (!empty($parserResult->activexcontrols)) {
            $activexSupport = $parserResult->activexcontrols;
        } else {
            $activexSupport = null;
        }

        $result->setCapability('supports_activex_controls', $activexSupport);

        return $result;
    }

    /**
     * checks the parser result for special keys
     *
     * @param \stdClass $allProperties  The parser result array
     * @param string    $propertyName   The name of the property to detect
     * @param boolean   $depended       If TRUE the parameter $dependingValue has to be set
     * @param string    $dependingValue An master value
     *
     * @return string|integer|boolean The value of the detected property
     */
    private function detectProperty(
        \stdClass $allProperties, $propertyName, $depended = false,
        $dependingValue = null
    ) {
        $propertyName  = strtolower($propertyName);
        $propertyValue = (empty($allProperties->$propertyName) ? null : trim($allProperties->$propertyName));

        if (empty($propertyValue)
            || '' == $propertyValue
        ) {
            $propertyValue = null;
        }

        if ($depended && null !== $propertyValue && !$dependingValue) {
            $propertyValue = null;
        }

        return $propertyValue;
    }
}