<?php
/**
 * This file is part of the mimmi20/ua-comparator package.
 *
 * Copyright (c) 2015-2023, Thomas Mueller <mimmi20@live.de>
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

/**
 * Browscap.ini parsing class with caching and update capabilities
 */
final class Browscap implements MapperInterface
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
        if (!isset($parserResult->browser)) {
            $browser = new Browser(null, null, null);
        } else {
            $browserName    = $this->mapper->mapBrowserName($parserResult->browser);
            $browserVersion = $this->mapper->mapBrowserVersion(
                $parserResult->version,
                $browserName,
            );

            if (!empty($parserResult->browser_type)) {
                $browserType = $parserResult->browser_type;
            } else {
                $browserType = null;
            }

            // if (!empty($parserResult->browser_modus) && 'unknown' !== $parserResult->browser_modus) {
            //    $browserModus = $parserResult->browser_modus;
            // } else {
            //    $browserModus = null;
            // }

            $browserManufacturer = null;
            $browserMakerKey     = $this->mapper->mapBrowserMaker($parserResult->browser_maker, $browserName);

            try {
                $browserManufacturer = (new CompanyLoader($this->cache))->load($browserMakerKey);
            } catch (NotFoundException) {
                // $this->logger->info($e);
            }

            $browser = new Browser(
                $browserName,
                $browserManufacturer,
                $browserVersion,
                $this->mapper->mapBrowserType($this->cache, $browserType),
                $parserResult->browser_bits,
            );
        }

        if (!isset($parserResult->device_code_name)) {
            $device = new Device(null, null, null, null);
        } else {
            $deviceName = $this->mapper->mapDeviceName($parserResult->device_code_name);

            $deviceManufacturer = null;
            $deviceMakerKey     = $this->mapper->mapDeviceMaker($parserResult->device_maker, $deviceName);

            try {
                $deviceManufacturer = (new CompanyLoader($this->cache))->load($deviceMakerKey);
            } catch (NotFoundException) {
                // $this->logger->info($e);
            }

            $deviceBrand    = null;
            $deviceBrandKey = $this->mapper->mapDeviceBrandName($parserResult->device_brand_name, $deviceName);

            try {
                $deviceBrand = (new CompanyLoader($this->cache))->load($deviceBrandKey);
            } catch (NotFoundException) {
                // $this->logger->info($e);
            }

            $device = new Device(
                $deviceName,
                $this->mapper->mapDeviceMarketingName($parserResult->device_name, $deviceName),
                $deviceManufacturer,
                $deviceBrand,
                $this->mapper->mapDeviceType($this->cache, $parserResult->device_type),
                $parserResult->device_pointing_method,
            );
        }

        if (!isset($parserResult->platform)) {
            $os = new Os(null, null);
        } else {
            $platform        = $this->mapper->mapOsName($parserResult->platform);
            $platformVersion = $this->mapper->mapOsVersion($parserResult->platform_version, $parserResult->platform);

            $osManufacturer = null;
            $osMakerKey     = $this->mapper->mapOsMaker($parserResult->platform_maker, $parserResult->platform);

            try {
                $osManufacturer = (new CompanyLoader($this->cache))->load($osMakerKey);
            } catch (NotFoundException) {
                // $this->logger->info($e);
            }

            $os = new Os(
                $platform,
                null,
                $osManufacturer,
                $platformVersion,
                $parserResult->platform_bits,
            );
        }

        if (!isset($parserResult->renderingengine_name)) {
            $engine = new Engine(null);
        } else {
            $engineName = $this->mapper->mapEngineName($parserResult->renderingengine_name);

            $engineManufacturer = null;

            try {
                $engineManufacturer = (new CompanyLoader($this->cache))->load($parserResult->renderingengine_maker);
            } catch (NotFoundException) {
                // $this->logger->info($e);
            }

            $engine = new Engine(
                $engineName,
                $engineManufacturer,
                $this->mapper->mapEngineVersion($parserResult->renderingengine_version),
            );
        }

        $requestFactory = new GenericRequestFactory();

        return new Result($requestFactory->createRequestForUserAgent($agent), $device, $os, $browser, $engine);
    }

    /** @throws void */
    public function getMapper(): InputMapper | null
    {
        return $this->mapper;
    }
}
