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
use UaComparator\Helper\InputMapper;
use UaResult\Result;
use UaResult\Version;
use UAS\Parser;
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
class UasParser implements ModuleInterface
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
     * @return \UaComparator\Module\UasParser
     */
    public function init()
    {
        $this->detect('');

        return $this;
    }

    /**
     * @param string $agent
     *
     * @return \UaComparator\Module\UasParser
     */
    public function detect($agent)
    {
        $parser = new Parser('data/cache/uasparser');

        $this->agent           = $agent;
        $this->detectionResult = $parser->Parse($agent);

        return $this;
    }

    /**
     * starts the detection timer
     *
     * @return \UaComparator\Module\UasParser
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
     * @return \UaComparator\Module\UasParser
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
     * @return \UaComparator\Module\UasParser
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

        $browserName    = $mapper->mapBrowserName($parserResult['ua_family']);
        $browserType    = $mapper->mapBrowserType($parserResult['typ'], $browserName);
        $browserVersion = $mapper->mapBrowserVersion($parserResult['ua_version'], $browserName);
        $browserMaker   = $mapper->mapBrowserMaker($parserResult['ua_company'], $browserName);

        $result->setCapability('browser_type', $browserType->getName());
        $result->setCapability('is_bot', $browserType->isBot());
        $result->setCapability('is_transcoder', $browserType->isTranscoder());
        $result->setCapability('is_syndication_reader', $browserType->isSyndicationReader());
        $result->setCapability('is_banned', $browserType->isBanned());
        $result->setCapability('mobile_browser', $browserName);
        $result->setCapability('mobile_browser_manufacturer', $browserMaker);
        $result->setCapability('mobile_browser_version', $browserVersion);

        $osName    = $mapper->mapOsName($parserResult['os_family']);
        $osVersion = null;
        $osMaker   = $mapper->mapOsMaker($parserResult['os_company'], $osName);

        $result->setCapability('device_os', $osName);

        $version = new Version();
        $version->setMode(
            Version::COMPLETE
            | Version::IGNORE_MINOR_IF_EMPTY
            | Version::IGNORE_MICRO_IF_EMPTY
        );

        $result->setCapability(
            'device_os_version',
            $version->setVersion($osVersion)
        );
        $result->setCapability('device_os_manufacturer', $osMaker);

        return $result;
    }
}
