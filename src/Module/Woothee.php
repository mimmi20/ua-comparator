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

namespace UaComparator\Module;

use DeviceDetector\Parser\Client\Browser;
use UaDataMapper\InputMapper;
use Monolog\Logger;
use UaResult\Result;
use Woothee\Classifier;
use WurflCache\Adapter\AdapterInterface;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 *
 * @category  UaComparator
 * @package   UaComparator
 * @author    Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 * @copyright 2015 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 */
class Woothee implements ModuleInterface
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
     * @var \Woothee\Classifier
     */
    private $detector = null;

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

        $this->detector = new Classifier();
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
        $resultRaw   = $this->detector->parse($agent);
        //var_dump($resultRaw);

        $this->detectionResult = $resultRaw;

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

        $browserName    = $mapper->mapBrowserName($parserResult['name']);
        $browserVersion = $mapper->mapBrowserVersion($parserResult['version'], $browserName);

        $result->setCapability('mobile_browser', $browserName);
        $result->setCapability('mobile_browser_version', $browserVersion);
        $result->setCapability(
            'browser_type',
            $mapper->mapBrowserType($parserResult['category'], $browserName)->getName()
        );

        if (isset($parserResult['os']) && !in_array($parserResult['os'], array('iPad', 'iPhone'))) {
            $osName    = $mapper->mapOsName($parserResult['os']);
            $osVersion = $mapper->mapOsVersion($parserResult['os_version'], $osName);

            $result->setCapability('device_os', $osName);
            $result->setCapability('device_os_version', $osVersion);
        }

        if (isset($parserResult['category'])
            && isset($parserResult['os'])
            && !in_array($parserResult['os'], array('iPad', 'iPhone'))
        ) {
            $result->setCapability('device_type', $mapper->mapDeviceType($parserResult['category']));
        }

        return $result;
    }
}
