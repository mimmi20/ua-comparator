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
 *
 * @author    Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 * @copyright 2015 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 *
 * @link      https://github.com/mimmi20/ua-comparator
 */

namespace UaComparator\Module\Mapper;

use UaDataMapper\InputMapper;
use UaResult\Browser\Browser;
use UaResult\Device\Device;
use UaResult\Engine\Engine;
use UaResult\Os\Os;
use UaResult\Result\Result;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 *
 * @category  UaComparator
 *
 * @author    Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 * @copyright 2015 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 */
class UserAgentApiCom implements MapperInterface
{
    /**
     * @var null|\UaDataMapper\InputMapper
     */
    private $mapper = null;

    /**
     * Gets the information about the browser by User Agent
     *
     * @param mixed  $parserResult
     * @param string $agent
     *
     * @return \UaResult\Result\Result the object containing the browsers details.
     */
    public function map($parserResult, $agent)
    {
        $browserName    = $this->mapper->mapBrowserName($parserResult->browser);
        $browserVersion = $this->mapper->mapBrowserVersion(
            $parserResult->version,
            $browserName
        );

        if (!empty($parserResult->browser_type)) {
            $browserType = $parserResult->browser_type;
        } else {
            $browserType = null;
        }

        if (!empty($parserResult->browser_modus) && 'unknown' !== $parserResult->browser_modus) {
            $browserModus = $parserResult->browser_modus;
        } else {
            $browserModus = null;
        }

        $browser = new Browser(
            $agent,
            [
                'name'         => $browserName,
                'modus'        => $browserModus,
                'version'      => $browserVersion,
                'manufacturer' => $this->mapper->mapBrowserMaker($parserResult->browser_maker, $browserName),
                'bits'         => $parserResult->browser_bits,
                'type'         => $this->mapper->mapBrowserType($browserType, $browserName),
            ]
        );

        $deviceName = $this->mapper->mapDeviceName($parserResult->device_code_name);

        $device = new Device(
            $agent,
            [
                'deviceName'     => $deviceName,
                'marketingName'  => $this->mapper->mapDeviceMarketingName($parserResult->device_name, $deviceName),
                'manufacturer'   => $this->mapper->mapDeviceMaker($parserResult->device_maker, $deviceName),
                'brand'          => $this->mapper->mapDeviceBrandName($parserResult->device_brand_name, $deviceName),
                'pointingMethod' => $parserResult->device_pointing_method,
                'type'           => $this->mapper->mapDeviceType($parserResult->device_type),
            ]
        );

        $platform        = $this->mapper->mapOsName($parserResult->platform);
        $platformVersion = $this->mapper->mapOsVersion($parserResult->platform_version, $platform);

        $os = new Os(
            $agent,
            [
                'name'         => $platform,
                'version'      => $platformVersion,
                'manufacturer' => $this->mapper->mapOsMaker($parserResult->platform_maker, $platform),
                'bits'         => $parserResult->platform_bits,
            ]
        );

        $engineName = $this->mapper->mapEngineName($parserResult->renderingengine_name);

        $engine = new Engine(
            $agent,
            [
                'name'         => $engineName,
                'version'      => $this->mapper->mapEngineVersion($parserResult->renderingengine_version),
                'manufacturer' => $parserResult->renderingengine_maker,
            ]
        );

        return new Result($agent, $device, $os, $browser, $engine);
        /*
        $result = new Result($agent);

        if (null === $parserResult) {
            return $result;
        }

        $browserName    = $this->mapper->mapBrowserName($parserResult->browser_name);
        $browserVersion = $this->mapper->mapBrowserVersion($parserResult->browser_version, $browserName);

        $result->setCapability('mobile_browser', $browserName);
        $result->setCapability('mobile_browser_version', $browserVersion);

        if (isset($parserResult->engine_name)) {
            $engineName = $parserResult->engine_name;

            if ('unknown' === $engineName || '' === $engineName) {
                $engineName = null;
            }

            $result->setCapability('renderingengine_name', $engineName);

            if (!empty($parserResult->engine_version)) {
                $engineVersion = $this->mapper->mapEngineVersion($parserResult->engine_version);
                $result->setCapability('renderingengine_version', $engineVersion);
            }
        }

        $deviceType = $parserResult->platform_type;
        $result->setCapability('device_type', $this->mapper->mapDeviceType($deviceType));

        return $result;
        /**/
    }

    /**
     * @return null|\UaDataMapper\InputMapper
     */
    public function getMapper()
    {
        return $this->mapper;
    }

    /**
     * @param \UaDataMapper\InputMapper $mapper
     *
     * @return \UaComparator\Module\Mapper\MapperInterface
     */
    public function setMapper(InputMapper $mapper)
    {
        $this->mapper = $mapper;

        return $this;
    }
}
