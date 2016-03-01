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
class PiwikDetector implements MapperInterface
{
    /**
     * @var null|\UaDataMapper\InputMapper
     */
    private $mapper = null;

    /**
     * Gets the information about the browser by User Agent
     *
     * @param \stdClass $parserResult
     * @param string    $agent
     *
     * @return \UaResult\Result\Result the object containing the browsers details.
     */
    public function map($parserResult, $agent)
    {
        $browserMaker   = null;
        $browserVersion = null;

        if (!empty($parserResult->bot)) {
            $browserName  = $this->mapper->mapBrowserName($parserResult->bot->name);

            if (!empty($parserResult->bot->producer->name)) {
                $browserMaker = $parserResult->bot->producer->name;
            }

            $browserType = $this->mapper->mapBrowserType('robot', $browserName)->getName();
        } else {
            $browserName    = $this->mapper->mapBrowserName($parserResult->client->name);
            $browserVersion = $this->mapper->mapBrowserVersion(
                $parserResult->client->version,
                $browserName
            );

            if (!empty($parserResult->client->type)) {
                $browserType = $parserResult->client->type;
            } else {
                $browserType = null;
            }

            $browserType  = $this->mapper->mapBrowserType($browserType, $browserName)->getName();
        }

        $browser = new Browser(
            $agent,
            [
                'name'    => $browserName,
                'modus'   => null,
                'version' => $browserVersion,
                'manufacturer' => $browserMaker,
                'bits'    => null,
                'type'    => $this->mapper->mapBrowserType($browserType, $browserName),
            ]
        );

        $deviceName = $this->mapper->mapDeviceName($parserResult->device->model);

        $device = new Device(
            $agent,
            [
                'deviceName'     => $deviceName,
                'marketingName'  => $this->mapper->mapDeviceMarketingName($deviceName),
                'manufacturer'   => null,
                'brand'          => $this->mapper->mapDeviceBrandName($parserResult->device->brand, $deviceName),
                'pointingMethod' => null,
                'type'           => $this->mapper->mapDeviceType($parserResult->device->type),
            ]
        );


        if (!empty($parserResult->os->name)) {
            $osName    = $this->mapper->mapOsName($parserResult->os->name);
            $osVersion = $this->mapper->mapOsVersion($parserResult->os->version, $osName);

            $os = new Os(
                $agent,
                [
                    'name' => $osName,
                    'version' => $osVersion,
                    'manufacturer' => null,
                    'bits'         => null,
                ]
            );
        } else {
            $os = new Os(
                $agent,
                []
            );
        }


        if (!empty($parserResult->client->engine)) {
            $engineName = $this->mapper->mapEngineName($parserResult->client->engine);

            $engine = new Engine(
                $agent,
                [
                    'name' => $engineName,
                    'version' => null,
                    'manufacturer' => null,
                ]
            );
        } else {
            $engine = new Engine(
                $agent,
                []
            );
        }

        return new Result($agent, $device, $os, $browser, $engine);
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
