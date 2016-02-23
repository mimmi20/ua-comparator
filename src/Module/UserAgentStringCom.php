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

namespace UaComparator\Module;

use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\Client\Browser;
use DeviceDetector\Parser\Device\DeviceParserAbstract;
use DeviceDetector\Parser\OperatingSystem;
use Monolog\Logger;
use UaDataMapper\InputMapper;
use UaResult\Result;
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
class UserAgentStringCom implements ModuleInterface
{
    /**
     * @var \Monolog\Logger
     */
    private $logger = null;

    /**
     * @var \WurflCache\Adapter\AdapterInterface
     */
    private $cache = null;

    /**
     * @var float
     */
    private $timer = 0.0;

    /**
     * @var float
     */
    private $duration = 0.0;

    /**
     * @var string
     */
    private $name = '';

    /**
     * @var int
     */
    private $id = 0;

    /**
     * @var array
     */
    private $detectionResult = null;

    /**
     * @var string
     */
    private $agent = '';

    /**
     * creates the module
     *
     * @param \Monolog\Logger                      $logger
     * @param \WurflCache\Adapter\AdapterInterface $cache
     */
    public function __construct(Logger $logger, AdapterInterface $cache)
    {
        $this->logger = $logger;
        $this->cache  = $cache;
    }

    /**
     * initializes the module
     *
     * @return \UaComparator\Module\CrossJoin
     */
    public function init()
    {
        DeviceParserAbstract::setVersionTruncation(DeviceParserAbstract::VERSION_TRUNCATION_NONE);

        $this->detect('');

        return $this;
    }

    /**
     * @param string $agent
     *
     * @return \UaComparator\Module\CrossJoin
     */
    public function detect($agent)
    {
        $this->agent           = $agent;

        $deviceDetector = new DeviceDetector($agent);
        $deviceDetector->parse();

        $osFamily      = OperatingSystem::getOsFamily($deviceDetector->getOs('short_name'));
        $browserFamily = Browser::getBrowserFamily($deviceDetector->getClient('short_name'));

        $processed = [
            'user_agent'     => $deviceDetector->getUserAgent(),
            'bot'            => ($deviceDetector->isBot() ? $deviceDetector->getBot() : []),
            'os'             => $deviceDetector->getOs(),
            'client'         => $deviceDetector->getClient(),
            'device'         => [
                'type'       => $deviceDetector->getDeviceName(),
                'brand'      => $deviceDetector->getBrand(),
                'model'      => $deviceDetector->getModel(),
            ],
            'os_family'      => $osFamily !== false ? $osFamily : 'Unknown',
            'browser_family' => $browserFamily !== false ? $browserFamily : 'Unknown',
        ];

        $this->detectionResult = $processed;

        return $this;
    }

    /**
     * starts the detection timer
     *
     * @return \UaComparator\Module\CrossJoin
     */
    public function startTimer()
    {
        $this->duration = 0.0;
        $this->timer    = microtime(true);

        return $this;
    }

    /**
     * stops the detection timer
     *
     * @return \UaComparator\Module\CrossJoin
     */
    public function endTimer()
    {
        $this->duration = microtime(true) - $this->timer;
        $this->timer    = 0.0;

        return $this;
    }

    /**
     * returns the duration
     *
     * @return float
     */
    public function getTime()
    {
        return $this->duration;
    }

    /**
     * returns the required memory
     *
     * @return int
     */
    public function getMemory()
    {
        return 0;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     *
     * @return \UaComparator\Module\CrossJoin
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return \UaComparator\Module\CrossJoin
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return \UaResult\Result
     */
    public function getDetectionResult()
    {
        return $this->map($this->detectionResult);
    }

    /**
     * Gets the information about the browser by User Agent
     *
     * @param array $parserResult
     *
     * @return \UaResult\Result
     */
    private function map(array $parserResult)
    {
        $result = new Result($this->agent, $this->logger);
        $mapper = new InputMapper();

        if (!empty($parserResult['bot'])) {
            $browserName  = $mapper->mapBrowserName($parserResult['bot']['name']);

            $result->setCapability('mobile_browser', $browserName);

            if (isset($parserResult['bot']['producer']['name'])) {
                $browserMaker = $parserResult['bot']['producer']['name'];
                $result->setCapability(
                    'mobile_browser_manufacturer',
                    $mapper->mapBrowserMaker($browserMaker, $browserName)
                );
            }

            $result->setCapability('browser_type', $mapper->mapBrowserType('robot', $browserName)->getName());

            return $result;
        }

        $browserName    = $mapper->mapBrowserName($parserResult['client']['name']);
        $browserVersion = $mapper->mapBrowserVersion($parserResult['client']['version'], $browserName);

        $result->setCapability('mobile_browser', $browserName);
        $result->setCapability('mobile_browser_version', $browserVersion);
        $result->setCapability('browser_type', $mapper->mapBrowserType('browser', $browserName)->getName());

        if (!empty($parserResult['client']['type'])) {
            $browserType = $parserResult['client']['type'];
        } else {
            $browserType = null;
        }

        $result->setCapability('browser_type', $mapper->mapBrowserType($browserType, $browserName)->getName());

        if (isset($parserResult['client']['engine'])) {
            $engineName = $parserResult['client']['engine'];

            if ('unknown' === $engineName || '' === $engineName) {
                $engineName = null;
            }

            $result->setCapability('renderingengine_name', $engineName);
        }

        if (isset($parserResult['os']['name'])) {
            $osName    = $mapper->mapOsName($parserResult['os']['name']);
            $osVersion = $mapper->mapOsVersion($parserResult['os']['version'], $osName);

            $result->setCapability('device_os', $osName);
            $result->setCapability('device_os_version', $osVersion);
        }

        $deviceType      = $parserResult['device']['type'];
        $deviceName      = $parserResult['device']['model'];
        $deviceBrandName = $parserResult['device']['brand'];

        $result->setCapability('device_type', $mapper->mapDeviceType($deviceType));
        $result->setCapability('marketing_name', $mapper->mapDeviceMarketingName($deviceName));
        $result->setCapability('brand_name', $mapper->mapDeviceBrandName($deviceBrandName, $deviceName));

        return $result;
    }
}
