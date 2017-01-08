<?php
/**
 * Copyright (c) 2015, Thomas Mueller <mimmi20@live.de>
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
 * @author    Thomas Mueller <mimmi20@live.de>
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
use Wurfl\Request\GenericRequestFactory;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 *
 * @category  UaComparator
 *
 * @author    Thomas Mueller <mimmi20@live.de>
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
            $browserName = $this->mapper->mapBrowserName($parserResult->bot->name);

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

            $browserType = $this->mapper->mapBrowserType($browserType, $browserName)->getName();
        }

        $browser = new Browser(
            $browserName,
            $browserMaker,
            null,
            $browserVersion,
            null,
            $this->mapper->mapBrowserType($browserType, $browserName)
        );

        $deviceName = $this->mapper->mapDeviceName($parserResult->device->model);

        $device = new Device(
            $deviceName,
            $this->mapper->mapDeviceMarketingName($deviceName),
            null,
            $this->mapper->mapDeviceBrandName($parserResult->device->brand, $deviceName),
            null,
            null,
            $this->mapper->mapDeviceType($parserResult->device->type)
        );

        $os = new Os(null, null, null, null);

        if (!empty($parserResult->os->name)) {
            $osName    = $this->mapper->mapOsName($parserResult->os->name);
            $osVersion = $this->mapper->mapOsVersion($parserResult->os->version, $parserResult->os->name);

            if (!in_array($osName, ['PlayStation'])) {
                $os = new Os($osName, null, null, null, $osVersion);
            }
        }

        if (!empty($parserResult->client->engine)) {
            $engineName = $this->mapper->mapEngineName($parserResult->client->engine);

            $engine = new Engine($engineName, null, null);
        } else {
            $engine = new Engine(null, null, null);
        }

        $requestFactory = new GenericRequestFactory();

        return new Result($requestFactory->createRequestForUserAgent($agent), $device, $os, $browser, $engine);
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
