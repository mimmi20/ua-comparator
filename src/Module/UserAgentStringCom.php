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
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request as GuzzleHttpRequest;
use Monolog\Logger;
use UaComparator\Helper\Request;
use UaComparator\Module\Check\CheckInterface;
use UaComparator\Module\Mapper\MapperInterface;
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
     * @var \GuzzleHttp\Psr7\Request
     */
    private $request = null;

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
     * @return \UaComparator\Module\UserAgentStringCom
     */
    public function init()
    {
        return $this;
    }

    /**
     * @param string $agent
     * @param array  $headers
     *
     * @return \UaComparator\Module\UserAgentStringCom
     */
    public function detect($agent, array $headers = [])
    {
        $this->agent = $agent;
        $body        = null;

        $params  = [$this->config['ua-key'] => $agent] + $this->config['params'];
        $headers = $headers + $this->config['headers'];

        if ('GET' === $this->config['method']) {
            $uri = $this->config['uri'] . '?' . http_build_query($params, null, '&');
        } else {
            $uri  = $this->config['uri'];
            $body = http_build_query($params, null, '&');
        }

        $this->request = new GuzzleHttpRequest($this->config['method'], $uri, $headers, $body);
        $requestHelper = new Request();

        $this->detectionResult = null;

        try {
            $this->detectionResult = $requestHelper->getResponse($this->request, new Client());
        } catch (RequestException $e) {
            $this->logger->error($e);
        }

        return $this;
    }

    /**
     * starts the detection timer
     *
     * @return \UaComparator\Module\UserAgentStringCom
     */
    public function startTimer()
    {
        $this->bench->start();

        return $this;
    }

    /**
     * stops the detection timer
     *
     * @return \UaComparator\Module\UserAgentStringCom
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
     * @return \UaComparator\Module\UserAgentStringCom
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
     * @return \UaComparator\Module\UserAgentStringCom
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
     * @return \UaComparator\Module\UserAgentStringCom
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
     * @return \UaComparator\Module\UserAgentStringCom
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
            return;
        }

        try {
            $return = $this->getCheck()->getResponse($this->detectionResult, $this->request, $this->agent);
        } catch (RequestException $e) {
            $this->logger->error($e);

            return;
        }

        if (isset($return->duration)) {
            $this->duration = $return->duration;

            unset($return->duration);
        }

        if (isset($return->memory)) {
            $this->memory = $return->memory;

            unset($return->memory);
        }

        try {
            if (isset($return->result)) {
                return $this->getMapper()->map($return->result, $this->agent);
            }

            return $this->getMapper()->map($return, $this->agent);
        } catch (\UnexpectedValueException $e) {
            $this->logger->error($e);
        }

        return;
    }
}
