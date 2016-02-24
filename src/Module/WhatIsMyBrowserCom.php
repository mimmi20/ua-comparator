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
use UaComparator\Helper\Request;
use UaDataMapper\InputMapper;
use UaResult\Result;
use WurflCache\Adapter\AdapterInterface;
use Psr\Http\Message\RequestInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request as GuzzleHttpRequest;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 *
 * @category  UaComparator
 *
 * @author    Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 * @copyright 2015 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 */
class WhatIsMyBrowserCom implements ModuleInterface
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
     * @var \stdClass|null
     */
    private $detectionResult = null;

    /**
     * @var string
     */
    private $agent = '';

    /**
     * @var string
     */
    private static $uri = 'http://api.whatismybrowser.com/api/v1/user_agent_parse';

    /**
     * @var string
     */
    private $apiKey = '';

    /**
     *
     * @var \GuzzleHttp\Client
     */
    private $client = null;

    /**
     * creates the module
     *
     * @param \Monolog\Logger                      $logger
     * @param \WurflCache\Adapter\AdapterInterface $cache
     * @param string|null                          $apiKey
     * @param \GuzzleHttp\Client|null              $client
     */
    public function __construct(Logger $logger, AdapterInterface $cache, $apiKey = null, Client $client = null)
    {
        $this->logger = $logger;
        $this->cache  = $cache;

        if (null !== $apiKey) {
            $this->apiKey = $apiKey;
        }

        if (null !== $client) {
            $this->client = $client;
        } else {
            $this->client = new Client();
        }
    }

    /**
     * initializes the module
     *
     * @return \UaComparator\Module\CrossJoin
     */
    public function init()
    {
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

        $params = [
            'user_key'   => $this->apiKey,
            'user_agent' => $agent,
        ];

        $body = http_build_query($params, null, '&');

        $request       = new GuzzleHttpRequest('POST', self::$uri, [], $body);
        $requestHelper = new Request();

        try {
            $response = $requestHelper->getResponse($request, $this->client);
        } catch (RequestException $e) {
            $this->logger->error($e);

            $this->detectionResult = null;

            return $this;
        }

        try {
            $this->detectionResult = $this->checkResponse($response, $request, $agent);
        } catch (RequestException $e) {
            $this->logger->error($e);

            $this->detectionResult = null;
        }

        return $this;
    }

    /**
     * @param \GuzzleHttp\Psr7\Response          $response
     * @param \Psr\Http\Message\RequestInterface $request
     * @param string                             $agent
     *
     * @throws \GuzzleHttp\Exception\RequestException
     * @return \stdClass
     */
    private function checkResponse(Response $response, RequestInterface $request, $agent)
    {
        /*
         * no json returned?
         */
        $contentType = $response->getHeader('Content-Type');

        if (! isset($contentType[0]) || $contentType[0] != 'application/json') {
            throw new RequestException('Could not get valid "application/json" response from "' . $request->getUri() . '". Response is "' . $response->getBody()->getContents() . '"', $request);
        }

        $content = json_decode($response->getBody()->getContents());

        /*
         * No result
         */
        if (isset($content->message_code) && $content->message_code == 'no_user_agent') {
            throw new RequestException('No result found for user agent: ' . $agent, $request);
        }

        /*
         * Limit exceeded
         */
        if (isset($content->message_code) && $content->message_code == 'usage_limit_exceeded') {
            throw new RequestException('Exceeded the maximum number of request with API key "' . $this->apiKey . '" for ' . $this->getName(), $request);
        }

        /*
         * Error
         */
        if (isset($content->message_code) && $content->message_code == 'no_api_user_key') {
            throw new RequestException('Missing API key for ' . $this->getName(), $request);
        }

        if (isset($content->message_code) && $content->message_code == 'user_key_invalid') {
            throw new RequestException('Your API key "' . $this->apiKey . '" is not valid for ' . $this->getName(), $request);
        }

        if (!isset($content->result) || $content->result !== 'success') {
            throw new RequestException('Could not get valid response from "' . $request->getUri() . '". Response is "' . $response->getBody()->getContents() . '"', $request);
        }

        /*
         * Missing data?
         */
        if (! $content instanceof \stdClass || ! isset($content->parse) || ! $content->parse instanceof \stdClass) {
            throw new RequestException('Could not get valid response from "' . $request->getUri() . '". Response is "' . print_r($content, true) . '"', $request);
        }

        return $content->parse;
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
     * @param \stdClass|null $parserResult
     *
     * @return \UaResult\Result
     */
    private function map(\stdClass $parserResult = null)
    {
        $result = new Result($this->agent, $this->logger);
        $mapper = new InputMapper();

        if (null === $parserResult) {
            return $result;
        }

        $browserName    = $mapper->mapBrowserName($parserResult->browser_name);
        $browserVersion = $mapper->mapBrowserVersion($parserResult->browser_version_full, $browserName);

        $result->setCapability('mobile_browser', $browserName);
        $result->setCapability('mobile_browser_version', $browserVersion);
        /*
        $result->setCapability('browser_type', $mapper->mapBrowserType('browser', $browserName)->getName());

        if (!empty($parserResult['client']['type'])) {
            $browserType = $parserResult['client']['type'];
        } else {
            $browserType = null;
        }

        $result->setCapability('browser_type', $mapper->mapBrowserType($browserType, $browserName)->getName());
        /**/

        if (isset($parserResult->layout_engine_name)) {
            $engineName = $parserResult->layout_engine_name;

            if ('unknown' === $engineName || '' === $engineName) {
                $engineName = null;
            }

            $result->setCapability('renderingengine_name', $engineName);

            if (!empty($parserResult->layout_engine_version)) {
                $engineVersion = $mapper->mapEngineVersion($parserResult->layout_engine_version);
                $result->setCapability('renderingengine_version', $engineVersion);
            }
        }

        if (isset($parserResult->operating_system_name)) {
            $osName    = $mapper->mapOsName($parserResult->operating_system_name);
            $osVersion = $mapper->mapOsVersion($parserResult->operating_system_version_full, $osName);

            $result->setCapability('device_os', $osName);
            $result->setCapability('device_os_version', $osVersion);
        }

        $deviceName      = $parserResult->operating_platform;
        $deviceBrandName = $parserResult->operating_platform_vendor_name;

        $result->setCapability('marketing_name', $mapper->mapDeviceMarketingName($deviceName));
        $result->setCapability('brand_name', $mapper->mapDeviceBrandName($deviceBrandName, $deviceName));

        return $result;
    }
}
