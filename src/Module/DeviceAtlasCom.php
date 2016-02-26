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
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request as GuzzleHttpRequest;
use GuzzleHttp\Psr7\Response;
use Monolog\Logger;
use Psr\Http\Message\RequestInterface;
use UaComparator\Helper\Request;
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
class DeviceAtlasCom implements ModuleInterface
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
    private static $uri = 'http://region0.deviceatlascloud.com/v1/detect/properties';

    /**
     * @var string
     */
    private $apiKey = '';

    /**
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
     * @param array  $headers
     *
     * @return \UaComparator\Module\CrossJoin
     */
    public function detect($agent, array $headers = [])
    {
        $this->agent = $agent;

        $parameters = '?licencekey=' . urlencode($this->apiKey);
        $parameters .= '&useragent=' . urlencode($agent);

        $uri = self::$uri . $parameters;

        // key to lower
        $headers = array_change_key_case($headers);

        $newHeaders = [];
        foreach ($headers as $key => $value) {
            $newHeaders['X-DA-' . $key] = $value;
        }

        $newHeaders['User-Agent'] = 'ThaDafinser/UserAgentParser:v1.4';

        $request       = new GuzzleHttpRequest('GET', $uri, $newHeaders);
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
            $this->detectionResult = null;

            /* @var $prevEx \GuzzleHttp\Exception\ClientException */
            $prevEx = $e->getPrevious();

            if ($prevEx->hasResponse() === true && $prevEx->getResponse()->getStatusCode() === 403) {
                $this->logger->error(new RequestException('Your API key "' . $this->apiKey . '" is not valid for ' . $this->getName(), $request, null, $e));

                return $this;
            }

            $this->logger->error($e);
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
        if (! isset($contentType[0]) || $contentType[0] !== 'application/json; charset=UTF-8') {
            throw new RequestException('Could not get valid "application/json" response from "' . $request->getUri() . '". Response is "' . $response->getBody()->getContents() . '"', $request);
        }

        $content = json_decode($response->getBody()->getContents());

        if (! $content instanceof \stdClass || ! isset($content->properties)) {
            throw new RequestException('Could not get valid response from "' . $request->getUri() . '". Response is "' . $response->getBody()->getContents() . '"', $request);
        }

        /*
         * No result found?
         */
        if (! $content->properties instanceof \stdClass || count((array) $content->properties) === 0) {
            throw new RequestException('No result found for user agent: ' . $agent, $request);
        }

        return $content->properties;
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

        $browserName    = $mapper->mapBrowserName($parserResult->browserName);
        $browserVersion = $mapper->mapBrowserVersion($parserResult->browserVersion, $browserName);

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

        if (isset($parserResult->browserRenderingEngine)) {
            $engineName = $parserResult->browserRenderingEngine;

            if ('unknown' === $engineName || '' === $engineName) {
                $engineName = null;
            }

            $result->setCapability('renderingengine_name', $engineName);
        }

        if (isset($parserResult->osName)) {
            $osName    = $mapper->mapOsName($parserResult->osName);
            $osVersion = $mapper->mapOsVersion($parserResult->osVersion, $osName);

            $result->setCapability('device_os', $osName);
            $result->setCapability('device_os_version', $osVersion);
        }

        $deviceType = $parserResult->primaryHardwareType;
        $result->setCapability('device_type', $mapper->mapDeviceType($deviceType));

        return $result;
    }
}
