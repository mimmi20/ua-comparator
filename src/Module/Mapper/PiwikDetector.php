<?php

/**
 * This file is part of the mimmi20/ua-comparator package.
 *
 * Copyright (c) 2015-2025, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace UaComparator\Module\Mapper;

use BrowserDetector\Loader\NotFoundException;
use Psr\Cache\CacheItemPoolInterface;
use stdClass;
use UaDataMapper\InputMapper;
use UaResult\Browser\Browser;
use UaResult\Company\CompanyLoader;
use UaResult\Device\Device;
use UaResult\Engine\Engine;
use UaResult\Os\Os;
use UaResult\Result\Result;
use Wurfl\Request\GenericRequestFactory;

use function in_array;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 */
final class PiwikDetector implements MapperInterface
{
    /** @throws void */
    public function __construct(
        private readonly InputMapper | null $mapper,
        private readonly CacheItemPoolInterface | null $cache,
    ) {
    }

    /**
     * Gets the information about the browser by User Agent
     *
     * @param stdClass $parserResult
     *
     * @return Result the object containing the browsers details
     *
     * @throws void
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
     */
    public function map($parserResult, string $agent): Result
    {
        $browserVersion      = null;
        $browserManufacturer = null;

        if (!empty($parserResult->bot)) {
            $browserName = $this->mapper->mapBrowserName($parserResult->bot->name);

            if (!empty($parserResult->bot->producer->name)) {
                $browserMakerKey = $this->mapper->mapBrowserMaker(
                    $parserResult->bot->producer->name,
                    $browserName,
                );

                try {
                    $browserManufacturer = (new CompanyLoader($this->cache))->load($browserMakerKey);
                } catch (NotFoundException) {
                    // $this->logger->info($e);
                }
            }

            $browserType = $this->mapper->mapBrowserType($this->cache, 'robot');
        } else {
            $browserName    = $this->mapper->mapBrowserName($parserResult->client->name);
            $browserVersion = $this->mapper->mapBrowserVersion(
                $parserResult->client->version,
                $browserName,
            );

            $browserType = !empty($parserResult->client->type)
                ? $this->mapper->mapBrowserType($this->cache, $parserResult->client->type)
                : null;
        }

        $browser = new Browser($browserName, $browserManufacturer, $browserVersion, $browserType);

        $deviceName = $this->mapper->mapDeviceName($parserResult->device->model);

        $deviceBrand    = null;
        $deviceBrandKey = $this->mapper->mapDeviceBrandName($parserResult->device->brand, $deviceName);

        try {
            $deviceBrand = (new CompanyLoader($this->cache))->load($deviceBrandKey);
        } catch (NotFoundException) {
            // $this->logger->info($e);
        }

        $device = new Device(
            $deviceName,
            $this->mapper->mapDeviceMarketingName($deviceName),
            null,
            $deviceBrand,
            $this->mapper->mapDeviceType($this->cache, $parserResult->device->type),
        );

        $os = new Os(null, null);

        if (!empty($parserResult->os->name)) {
            $osName    = $this->mapper->mapOsName($parserResult->os->name);
            $osVersion = $this->mapper->mapOsVersion(
                $parserResult->os->version,
                $parserResult->os->name,
            );

            if (!in_array($osName, ['PlayStation'], true)) {
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

        return new Result(
            $requestFactory->createRequestForUserAgent($agent),
            $device,
            $os,
            $browser,
            $engine,
        );
    }

    /** @throws void */
    public function getMapper(): InputMapper | null
    {
        return $this->mapper;
    }
}
