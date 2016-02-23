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

use Monolog\Logger;
use Sinergi\BrowserDetector;
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
class SinergiBrowserDetector implements ModuleInterface
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
        $this->agent = $agent;

        $this->detectionResult = [
            'browser'         => new BrowserDetector\Browser($agent),
            'operatingSystem' => new BrowserDetector\Os($agent),
            'device'          => new BrowserDetector\Device($agent),
        ];

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
        file_put_contents($this->getName() . '.txt', var_export($this->detectionResult, true), FILE_TEXT);

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

        /** @var BrowserDetector\Browser $browserRaw */
        $browserRaw = $parserResult['browser'];

        /** @var BrowserDetector\Os $osRaw */
        $osRaw = $parserResult['operatingSystem'];

        /** @var BrowserDetector\Device $deviceRaw */
        $deviceRaw  = $parserResult['device'];

        $browserName    = $mapper->mapBrowserName($browserRaw->getName());
        $browserVersion = $mapper->mapBrowserVersion($browserRaw->getVersion(), $browserName);

        $result->setCapability('mobile_browser', $browserName);
        $result->setCapability('mobile_browser_version', $browserVersion);

        if ($browserRaw->isRobot()) {
            $result->setCapability('browser_type', $mapper->mapBrowserType('robot', $browserName)->getName());
        }

        if ($osRaw->getName()) {
            $osName    = $mapper->mapOsName($osRaw->getName());
            $osVersion = $mapper->mapOsVersion($osRaw->getVersion(), $osName);

            $result->setCapability('device_os', $osName);
            $result->setCapability('device_os_version', $osVersion);
        }

        $result->setCapability('marketing_name', $mapper->mapDeviceMarketingName($deviceRaw->getName()));

        return $result;
    }
}
