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

use Monolog\Logger;
use Wurfl\Configuration\XmlConfig;
use Wurfl\Manager;
use Wurfl\Storage\Storage;
use WurflCache\Adapter\AdapterInterface;
use WurflCache\Adapter\Memory;
use Exception;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 *
 * @category  UaComparator
 * @package   UaComparator
 * @author    Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 * @copyright 2015 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 */
class Wurfl implements ModuleInterface
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
     * @var string
     */
    private $configFile = '';

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
     * @var mixed
     */
    private $detectionResult = null;

    /**
     * creates the module
     *
     * @param \Monolog\Logger                      $logger
     * @param \WurflCache\Adapter\AdapterInterface $cache
     * @param string                               $configFile
     */
    public function __construct(Logger $logger, AdapterInterface $cache, $configFile = '')
    {
        $this->logger     = $logger;
        $this->cache      = $cache;
        $this->configFile = $configFile;
    }

    /**
     * initializes the module
     *
     * @return \UaComparator\Module\Wurfl
     */
    public function init()
    {
        $this->detect('');

        $device = $this->getDetectionResult();
        $device->getAllCapabilities();

        return $this;
    }

    /**
     * @param string $agent
     *
     * @return \UaComparator\Module\Wurfl
     */
    public function detect($agent)
    {
        $wurflConfig      = new XmlConfig($this->configFile);
        $wurflCache       = new Storage(new Memory());
        $persistanceCache = new Storage($this->cache);
        $wurflManager     = new Manager($wurflConfig, $persistanceCache, $wurflCache);

        $agent = str_replace('Toolbar', '', $agent);

        try {
            $this->detectionResult = $wurflManager->getDeviceForUserAgent($agent);
        } catch (\Exception $e) {
            $this->logger->info($e);

            $this->detectionResult = null;
        }

        return $this;
    }

    /**
     * starts the detection timer
     *
     * @return \UaComparator\Module\Wurfl
     */
    public function startTimer()
    {
        $this->duration = 0.0;
        $this->timer    = microtime(true);

        return $this;
    }

    /**
     * stops the detection timer
     * @return float
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
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     *
     * @return \UaComparator\Module\Wurfl
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
     * @return \UaComparator\Module\Wurfl
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return \BrowserDetector\Detector\Result\Result
     */
    public function getDetectionResult()
    {
        $mapper = new Mapper\Wurfl();
        return $mapper->map($this->detectionResult);
    }
}
