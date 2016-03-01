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

use DeviceDetector\Parser\Client\Browser;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request as GuzzleHttpRequest;
use GuzzleHttp\Psr7\Response;
use Monolog\Logger;
use Psr\Http\Message\RequestInterface;
use UaComparator\Helper\Request;
use UaDataMapper\InputMapper;
use UaResult\Result\Result;
use WurflCache\Adapter\AdapterInterface;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 *
 * @category  UaComparator
 *
 * @author    Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 * @copyright 2015 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 */
class UdgerCom implements MapperInterface
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
        $result = new Result($agent);

        if (null === $parserResult) {
            return $result;
        }

        $browserName    = $this->mapper->mapBrowserName($parserResult->ua_family);
        $browserVersion = $this->mapper->mapBrowserVersion($parserResult->ua_ver, $browserName);

        $result->setCapability('mobile_browser', $browserName);
        $result->setCapability('mobile_browser_version', $browserVersion);
        /*
        $result->setCapability('browser_type', $this->mapper->mapBrowserType('browser', $browserName)->getName());

        if (!empty($parserResult['client']['type'])) {
            $browserType = $parserResult['client']['type'];
        } else {
            $browserType = null;
        }

        $result->setCapability('browser_type', $this->mapper->mapBrowserType($browserType, $browserName)->getName());
        /**/

        if (isset($parserResult->ua_engine)) {
            $engineName = $parserResult->ua_engine;

            if ('unknown' === $engineName || '' === $engineName) {
                $engineName = null;
            }

            $result->setCapability('renderingengine_name', $engineName);

            /*
            if (!empty($parserResult->layout_engine_version)) {
                $engineVersion = $this->mapper->mapEngineVersion($parserResult->layout_engine_version);
                $result->setCapability('renderingengine_version', $engineVersion);
            }
            /**/
        }

        if (isset($parserResult->os_family)) {
            $osName    = $this->mapper->mapOsName($parserResult->os_family);
            //$osVersion = $this->mapper->mapOsVersion($parserResult->operating_system_version_full, $osName);

            $result->setCapability('device_os', $osName);
            //$result->setCapability('device_os_version', $osVersion);
        }

        $deviceType = $parserResult->device_name;
        $result->setCapability('device_type', $this->mapper->mapDeviceType($deviceType));

        return $result;
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