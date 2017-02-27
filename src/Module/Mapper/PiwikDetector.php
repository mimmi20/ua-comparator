<?php


namespace UaComparator\Module\Mapper;

use BrowserDetector\Loader\NotFoundException;
use Psr\Cache\CacheItemPoolInterface;
use UaDataMapper\InputMapper;
use UaResult\Browser\Browser;
use UaResult\Company\CompanyLoader;
use UaResult\Device\Device;
use UaResult\Engine\Engine;
use UaResult\Os\Os;
use UaResult\Result\Result;
use Wurfl\Request\GenericRequestFactory;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 *
 * @category  UaComparator
 *
 * @author    Thomas Mueller <mimmi20@live.de>
 * @copyright 2015 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 */
class PiwikDetector implements MapperInterface
{
    /**
     * @var \UaDataMapper\InputMapper|null
     */
    private $mapper = null;

    /**
     * @var \Psr\Cache\CacheItemPoolInterface|null
     */
    private $cache = null;

    /**
     * @param \UaDataMapper\InputMapper         $mapper
     * @param \Psr\Cache\CacheItemPoolInterface $cache
     */
    public function __construct(InputMapper $mapper, CacheItemPoolInterface $cache)
    {
        $this->mapper = $mapper;
        $this->cache  = $cache;
    }

    /**
     * Gets the information about the browser by User Agent
     *
     * @param \stdClass $parserResult
     * @param string    $agent
     *
     * @return \UaResult\Result\Result the object containing the browsers details.
     */
    public function map($parserResult, $agent)
    {
        $browserVersion      = null;
        $browserManufacturer = null;

        if (!empty($parserResult->bot)) {
            $browserName = $this->mapper->mapBrowserName($parserResult->bot->name);

            if (!empty($parserResult->bot->producer->name)) {
                $browserMakerKey = $this->mapper->mapBrowserMaker($parserResult->bot->producer->name, $browserName);
                try {
                    $browserManufacturer = (new CompanyLoader($this->cache))->load($browserMakerKey);
                } catch (NotFoundException $e) {
                    //$this->logger->info($e);
                }
            }

            $browserType = $this->mapper->mapBrowserType($this->cache, 'robot');
        } else {
            $browserName    = $this->mapper->mapBrowserName($parserResult->client->name);
            $browserVersion = $this->mapper->mapBrowserVersion(
                $parserResult->client->version,
                $browserName
            );

            if (!empty($parserResult->client->type)) {
                $browserType = $this->mapper->mapBrowserType($this->cache, $parserResult->client->type);
            } else {
                $browserType = null;
            }
        }

        $browser = new Browser(
            $browserName,
            $browserManufacturer,
            $browserVersion,
            $browserType
        );

        $deviceName = $this->mapper->mapDeviceName($parserResult->device->model);

        $deviceBrand    = null;
        $deviceBrandKey = $this->mapper->mapDeviceBrandName($parserResult->device->brand, $deviceName);
        try {
            $deviceBrand = (new CompanyLoader($this->cache))->load($deviceBrandKey);
        } catch (NotFoundException $e) {
            //$this->logger->info($e);
        }

        $device = new Device(
            $deviceName,
            $this->mapper->mapDeviceMarketingName($deviceName),
            null,
            $deviceBrand,
            $this->mapper->mapDeviceType($this->cache, $parserResult->device->type)
        );

        $os = new Os(null, null);

        if (!empty($parserResult->os->name)) {
            $osName    = $this->mapper->mapOsName($parserResult->os->name);
            $osVersion = $this->mapper->mapOsVersion($parserResult->os->version, $parserResult->os->name);

            if (!in_array($osName, ['PlayStation'])) {
                $os = new Os($osName, null, null, $osVersion);
            }
        }

        if (!empty($parserResult->client->engine)) {
            $engineName = $this->mapper->mapEngineName($parserResult->client->engine);

            $engine = new Engine($engineName);
        } else {
            $engine = new Engine(null);
        }

        $requestFactory = new GenericRequestFactory();

        return new Result($requestFactory->createRequestForUserAgent($agent), $device, $os, $browser, $engine);
    }

    /**
     * @return null|\UaDataMapper\InputMapper
     */
    public function getMapper()
    {
        return $this->mapper;
    }
}
