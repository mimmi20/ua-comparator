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

use Psr\Cache\CacheItemPoolInterface;
use stdClass;
use UaDataMapper\InputMapper;
use UaResult\Browser\Browser;
use UaResult\Device\Device;
use UaResult\Engine\Engine;
use UaResult\Os\Os;
use UaResult\Result\Result;
use Wurfl\Request\GenericRequestFactory;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 */
final readonly class WhichBrowser implements MapperInterface
{
    /** @throws void */
    public function __construct(private InputMapper | null $mapper, private CacheItemPoolInterface | null $cache)
    {
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
        $browserName = $this->mapper->mapBrowserName($parserResult->browser->name);

        $browserVersion = empty($parserResult->browser->version->value)
            ? null
            : $this->mapper->mapBrowserVersion($parserResult->browser->version->value, $browserName);

        $browserType = !empty($parserResult->browser->type)
            ? $this->mapper->mapBrowserType($this->cache, $parserResult->browser->type)
            : null;

        $browser = new Browser($browserName, null, $browserVersion, $browserType);

        $device = new Device(
            $parserResult->device->model,
            $this->mapper->mapDeviceMarketingName($parserResult->device->model),
            null,
            null,
            $this->mapper->mapDeviceType($this->cache, $parserResult->device->type),
        );

        $platform = $this->mapper->mapOsName($parserResult->os->name);

        $platformVersion = empty($parserResult->os->version->value)
            ? null
            : $this->mapper->mapOsVersion($parserResult->os->version->value, $platform);

        $os = new Os($platform, null, null, $platformVersion);

        $engineVersion = empty($parserResult->engine->version->value)
            ? null
            : $this->mapper->mapEngineVersion($parserResult->engine->version->value);

        $engine = new Engine(
            $this->mapper->mapEngineName($parserResult->engine->name),
            null,
            $engineVersion,
        );

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
