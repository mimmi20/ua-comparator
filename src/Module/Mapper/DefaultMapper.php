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

use BrowserDetector\Version\Exception\NotNumericException;
use BrowserDetector\Version\NullVersion;
use stdClass;
use UaBrowserType\Type;
use UaDataMapper\InputMapper;
use UaResult\Browser\Browser;
use UaResult\Company\Company;
use UaResult\Device\Device;
use UaResult\Device\Display;
use UaResult\Engine\Engine;
use UaResult\Os\Os;
use UaResult\Result\Result;

use function in_array;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 */
final readonly class DefaultMapper implements MapperInterface
{
    /** @throws void */
    public function __construct(private InputMapper $mapper)
    {
    }

    /**
     * Gets the information about the browser by User Agent
     *
     * @param stdClass $parserResult
     *
     * @return Result the object containing the browsers details
     *
     * @throws NotNumericException
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    public function map(mixed $parserResult, string $agent): Result
    {
        $browserName = $this->mapper->mapBrowserName($parserResult->result->parsed->client->name);

        $browserManufacturer = $this->mapper->mapBrowserMaker(
            (string) $parserResult->result->parsed->client->manufacturer,
            $browserName,
        );

        $browserVersion = $this->mapper->mapBrowserVersion(
            (string) $parserResult->result->parsed->client->version,
            $browserName,
        );

        $browserType = !empty($parserResult->result->parsed->client->type)
            ? $this->mapper->mapBrowserType($parserResult->result->parsed->client->type)
            : Type::Unknown;

        $browser = new Browser(
            $browserName,
            new Company(type: 'unknown', name: $browserManufacturer, brandname: null),
            $browserVersion,
            $browserType,
        );

        $deviceName = $this->mapper->mapDeviceName($parserResult->result->parsed->device->deviceName);

        $deviceBrandKey = $this->mapper->mapDeviceBrandName(
            $parserResult->result->parsed->device->brand,
            $deviceName,
        );
        $deviceMakerKey = $this->mapper->mapDeviceMaker(
            (string) $parserResult->result->parsed->device->manufacturer,
            $deviceName,
        );

        $device = new Device(
            deviceName: $deviceName,
            marketingName: $this->mapper->mapDeviceMarketingName($deviceName),
            manufacturer: new Company(type: 'unknown', name: $deviceMakerKey, brandname: null),
            brand: new Company(type: 'unknown', name: $deviceBrandKey, brandname: null),
            type: $this->mapper->mapDeviceType($parserResult->result->parsed->device->type),
            display: new Display(),
            dualOrientation: null,
            simCount: null,
        );

        $os = new Os(
            name: null,
            marketingName: null,
            manufacturer: new Company(type: 'unknown', name: null, brandname: null),
            version: new NullVersion(),
            bits: null,
        );

        if (!empty($parserResult->result->parsed->os->name)) {
            $osName    = $this->mapper->mapOsName($parserResult->result->parsed->os->name);
            $osVersion = $this->mapper->mapOsVersion(
                $parserResult->result->parsed->os->version,
                $parserResult->result->parsed->os->name,
            );

            if (!in_array($osName, ['PlayStation'], true)) {
                $os = new Os(
                    $osName,
                    null,
                    new Company(type: 'unknown', name: null, brandname: null),
                    $osVersion,
                );
            }
        }

        if (!empty($parserResult->result->parsed->client->engine)) {
            $engineName = $this->mapper->mapEngineName($parserResult->result->parsed->client->engine);

            $engine = new Engine(
                name: $engineName,
                manufacturer: new Company(type: 'unknown', name: null, brandname: null),
                version: new NullVersion(),
            );
        } else {
            $engine = new Engine(
                name: null,
                manufacturer: new Company(type: 'unknown', name: null, brandname: null),
                version: new NullVersion(),
            );
        }

        return new Result((array) $parserResult->headers, $device, $os, $browser, $engine);
    }
}
