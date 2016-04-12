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

use GuzzleHttp\Client;
use Monolog\Logger;
use UaComparator\Module\Check\CheckInterface;
use UaComparator\Module\Mapper\MapperInterface;
use UserAgentParser\Exception\ExceptionInterface;
use UserAgentParser\Provider\Http;
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
class UdgerCom implements ModuleInterface
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
    private $name = '';

    /**
     * @var \GuzzleHttp\Psr7\Response|null
     */
    private $detectionResult = null;

    /**
     * @var string
     */
    private $agent = '';

    /**
     * @var null|\Ubench
     */
    private $bench = null;

    /**
     * @var null|array
     */
    private $config = null;

    /**
     * @var null|\UaComparator\Module\Check\CheckInterface
     */
    private $check = null;

    /**
     * @var null|\UaComparator\Module\Mapper\MapperInterface
     */
    private $mapper = null;

    /**
     * @var float
     */
    private $duration = 0.0;

    /**
     * @var int
     */
    private $memory = 0;

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

        $this->bench = new \Ubench();
    }

    /**
     * initializes the module
     *
     * @return \UaComparator\Module\UdgerCom
     */
    public function init()
    {
        return $this;
    }

    /**
     * @param string $agent
     * @param array  $headers
     *
     * @return \UaComparator\Module\UdgerCom
     */
    public function detect($agent, array $headers = [])
    {
        $this->agent           = $agent;
        $this->detectionResult = null;

        $parser = new Http\UdgerCom(
            new Client(),
            $this->config['params']['user-id'],
            $this->config['params']['api-key']
        );

        try {
            $this->detectionResult = $parser->parse($agent)->getProviderResultRaw();
        } catch (ExceptionInterface $e) {
            $this->logger->error($e);
        }

        return $this;
    }

    /**
     * starts the detection timer
     *
     * @return \UaComparator\Module\UdgerCom
     */
    public function startTimer()
    {
        $this->bench->start();

        return $this;
    }

    /**
     * stops the detection timer
     *
     * @return \UaComparator\Module\UdgerCom
     */
    public function endTimer()
    {
        $this->bench->end();

        $this->duration = $this->bench->getTime(true);
        $this->memory   = $this->bench->getMemoryPeak(true);

        return $this;
    }

    /**
     * returns the needed time
     *
     * @return float
     */
    public function getTime()
    {
        return $this->duration;
    }

    /**
     * returns the maximum needed memory
     *
     * @return int
     */
    public function getMaxMemory()
    {
        return $this->memory;
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
     * @return \UaComparator\Module\UdgerCom
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return array|null
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param array $config
     *
     * @return \UaComparator\Module\UdgerCom
     */
    public function setConfig(array $config)
    {
        $this->config = $config;

        return $this;
    }

    /**
     * @return null|\UaComparator\Module\Check\CheckInterface
     */
    public function getCheck()
    {
        return $this->check;
    }

    /**
     * @param \UaComparator\Module\Check\CheckInterface $check
     *
     * @return \UaComparator\Module\UdgerCom
     */
    public function setCheck(CheckInterface $check)
    {
        $this->check = $check;

        return $this;
    }

    /**
     * @return null|\UaComparator\Module\Mapper\MapperInterface
     */
    public function getMapper()
    {
        return $this->mapper;
    }

    /**
     * @param \UaComparator\Module\Mapper\MapperInterface $mapper
     *
     * @return \UaComparator\Module\UdgerCom
     */
    public function setMapper(MapperInterface $mapper)
    {
        $this->mapper = $mapper;

        return $this;
    }

    /**
     * @return \UaResult\Result\Result|null
     */
    public function getDetectionResult()
    {
        if (null === $this->detectionResult) {
            return null;
        }

        try {
            return $this->getMapper()->map($this->detectionResult, $this->agent);
        } catch (\UnexpectedValueException $e) {
            $this->logger->error($e);
        }

        return null;
    }
}
