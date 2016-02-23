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

use DeviceDetector\Parser\Client\Browser;
use Monolog\Logger;
use UaDataMapper\InputMapper;
use UaResult\Result;
use WhichBrowser\Parser;
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
class WhichBrowser implements ModuleInterface
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
     * @var \WhichBrowser\Parser
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
        $this->agent           = $agent;
        $this->detectionResult = new Parser(['User-Agent' => $agent]);

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
     * @param \WhichBrowser\Parser $parser
     *
     * @return \UaResult\Result
     */
    private function map(Parser $parser)
    {
        $result = new Result($this->agent, $this->logger);
        $mapper = new InputMapper();

        $browserName = null;
        //var_dump($parser);
        if (isset($parser->browser->name)) {
            $browserName = $mapper->mapBrowserName($parser->browser->name);
            $result->setCapability('mobile_browser', $browserName);

            if (null !== $parser->browser->version) {
                $browserVersion = $mapper->mapBrowserVersion($parser->browser->version->toString(), $browserName);
                $result->setCapability('mobile_browser_version', $browserVersion);
            }
        }

        $result->setCapability('browser_type', $mapper->mapBrowserType($parser->browser->type, $browserName)->getName());

        if (isset($parser->engine->name)) {
            $engineName = $parser->engine->name;

            if ('unknown' === $engineName || '' === $engineName) {
                $engineName = null;
            }

            $result->setCapability('renderingengine_name', $engineName);

            if (!empty($parser->engine->version)) {
                $engineVersion = $mapper->mapEngineVersion($parser->engine->version->toString());
                $result->setCapability('renderingengine_version', $engineVersion);
            }
        }

        if (isset($parser->os->name)) {
            $osName    = $mapper->mapOsName($parser->os->name);
            $result->setCapability('device_os', $osName);

            if (null !== $parser->os->version) {
                $osVersion = $mapper->mapOsVersion($parser->os->version->toString(), $osName);
                $result->setCapability('device_os_version', $osVersion);
            }
        }

        $deviceType = $parser->device->type;
        $result->setCapability('device_type', $mapper->mapDeviceType($deviceType));

        if (isset($parser->device->model)) {
            $deviceName = $parser->device->model;
            $result->setCapability('marketing_name', $mapper->mapDeviceMarketingName($deviceName));
        }

        return $result;
    }
}
