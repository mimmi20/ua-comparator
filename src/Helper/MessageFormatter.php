<?php

/**
 * This file is part of the mimmi20/ua-comparator package.
 *
 * Copyright (c) 2015-2026, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace UaComparator\Helper;

use BrowserDetector\Version\VersionBuilder;
use BrowserDetector\Version\VersionInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use UaDeviceType\Type;
use UaResult\Bits\Bits;
use UaResult\Browser\Browser;
use UaResult\Browser\BrowserInterface;
use UaResult\Device\Architecture;
use UaResult\Device\Device;
use UaResult\Device\DeviceInterface;
use UaResult\Device\Display;
use UaResult\Device\DisplayInterface;
use UaResult\Engine\Engine;
use UaResult\Engine\EngineInterface;
use UaResult\Os\Os;
use UaResult\Os\OsInterface;
use UaResult\Result\Result;
use UnexpectedValueException;
use ValueError;

use function array_key_exists;
use function array_keys;
use function in_array;
use function mb_str_pad;
use function mb_strlen;
use function mb_strpos;
use function mb_strtolower;
use function mb_substr;

/**
 * BrowserDetectorModule.ini parsing class with caching and update capabilities
 */
final readonly class MessageFormatter
{
    private $companyLoader;

    /**
     * @param array<Result> $collection
     *
     * @throws void
     */
    public function __construct(private array $collection, private int $columnsLength)
    {
        // nothing to do
    }

    /**
     * @return array<string>
     *
     * @throws UnexpectedValueException
     */
    public function formatMessage(string $propertyName, LoggerInterface $logger): array
    {
        $modules      = array_keys($this->collection);
        $firstElement = $this->collection[$modules[0]];

        $strReality = $firstElement === null ? '(NULL)' : $this->getValue(
            $this->fromArray($logger, (array) $firstElement),
            $propertyName,
        );

        $detectionResults = [];

        foreach ($modules as $module => $name) {
            $element = $this->collection[$name];

            $strTarget = $element === null ? '(NULL)' : $this->getValue(
                $this->fromArray($logger, (array) $element),
                $propertyName,
            );

            if (mb_strtolower($strTarget) === mb_strtolower($strReality)) {
                $r1 = ' ';
            } elseif (
                in_array($strReality, ['(NULL)', '', '(empty)'], strict: true)
                || in_array($strTarget, ['(NULL)', '', '(empty)'], strict: true)
            ) {
                $r1 = ' ';
            } elseif (
                (mb_strlen($strTarget) > mb_strlen($strReality))
                && (0 < mb_strlen($strReality))
                && (mb_strpos($strTarget, $strReality) === 0)
            ) {
                $r1 = '-';
            } elseif (
                (mb_strlen($strTarget) < mb_strlen($strReality))
                && (0 < mb_strlen($strTarget))
                && (mb_strpos($strReality, $strTarget) === 0)
            ) {
                $r1 = ' ';
            } else {
                $r1 = '-';
            }

            $result = $r1 . $strTarget;

            if (mb_strlen($result) > $this->columnsLength) {
                $result = mb_substr($result, 0, $this->columnsLength - 3) . '...';
            }

            $detectionResults[$module] = mb_str_pad($result, $this->columnsLength, ' ');
        }

        return $detectionResults;
    }

    /** @throws UnexpectedValueException */
    private function getValue(Result $element, string $propertyName): string
    {
        $value = match ($propertyName) {
            'mobile_browser' => $element->getBrowser()->getName(),
            'mobile_browser_version' => $element->getBrowser()->getVersion()->getVersion(
                VersionInterface::IGNORE_MICRO_IF_EMPTY | VersionInterface::IGNORE_MINOR_IF_EMPTY,
            ),
            'mobile_browser_modus' => $element->getBrowser()->getModus(),
            'mobile_browser_bits' => $element->getBrowser()->getBits(),
            'browser_type' => $element->getBrowser()->getType()->getName(),
            'mobile_browser_manufacturer' => $element->getBrowser()->getManufacturer()->getName(),
            'renderingengine_name' => $element->getEngine()->getName(),
            'renderingengine_version' => $element->getEngine()->getVersion()->getVersion(
                VersionInterface::IGNORE_MICRO_IF_EMPTY | VersionInterface::IGNORE_MINOR_IF_EMPTY,
            ),
            'renderingengine_manufacturer' => $element->getEngine()->getManufacturer()->getName(),
            'device_os' => $element->getOs()->getName(),
            'device_os_version' => $element->getOs()->getVersion()->getVersion(
                VersionInterface::IGNORE_MICRO_IF_EMPTY | VersionInterface::IGNORE_MINOR_IF_EMPTY,
            ),
            'device_os_bits' => $element->getOs()->getBits(),
            'device_os_manufacturer' => $element->getOs()->getManufacturer()->getName(),
            'brand_name' => $element->getDevice()->getBrand()->getBrandName(),
            'marketing_name' => $element->getDevice()->getMarketingName(),
            'model_name' => $element->getDevice()->getDeviceName(),
            'manufacturer_name' => $element->getDevice()->getManufacturer()->getName(),
            'device_type' => $element->getDevice()->getType()->getName(),
            'resolution_width' => $element->getDevice()->getDisplay()->getWidth(),
            'resolution_height' => $element->getDevice()->getDisplay()->getHeight(),
            'dual_orientation' => $element->getDevice()->getDualOrientation(),
            default => '(n/a)',
        };

        if ($value === null || $value === 'null') {
            $output = '(NULL)';
        } elseif ($value === '') {
            $output = '(empty)';
        } elseif ($value === false || $value === 'false') {
            $output = '(false)';
        } elseif ($value === true || $value === 'true') {
            $output = '(true)';
        } else {
            $output = (string) $value;
        }

        return $output;
    }

    /**
     * @param array<string, mixed> $data
     * @throws void
     */
    private function fromArray(LoggerInterface $logger, array $data): Result
    {
        $headers = [];

        if (array_key_exists('headers', $data)) {
            $headers = (array) $data['headers'];
        }

        $device = null;

        if (array_key_exists('device', $data)) {
            /**
             * @param LoggerInterface $logger
             * @param array                    $data
             *
             * @return DeviceInterface
             */
            $fromArray = function (LoggerInterface $logger, array $data): DeviceInterface {
                $deviceName      = $data['deviceName'] ?? null;
                $marketingName   = $data['marketingName'] ?? null;
                $dualOrientation = array_key_exists('dualOrientation', $data)
                    ? $data['dualOrientation']
                    : false;

                $type = null;

                if (array_key_exists('type', $data)) {
                    try {
                        $type = Type::from($data['type']);
                    } catch (ValueError $e) {
                        $logger->info($e);
                    }
                }

                $manufacturer = null;

                if (array_key_exists('manufacturer', $data)) {
                    try {
                        $manufacturer = $this->companyLoader->load($data['manufacturer']);
                    } catch (NotFoundException $e) {
                        $logger->info($e);
                    }
                }

                $brand = null;

                if (array_key_exists('brand', $data)) {
                    try {
                        $brand = $this->companyLoader->load($data['brand']);
                    } catch (NotFoundException $e) {
                        $logger->info($e);
                    }
                }

                $display = null;

                if (array_key_exists('display', $data)) {
                    /**
                     * @param array                    $data
                     *
                     * @return DisplayInterface
                     */
                    $fromArray = static function (array $data): DisplayInterface {
                        $width  = $data['width'] ?? null;
                        $height = $data['height'] ?? null;
                        $touch  = $data['touch'] ?? null;
                        $size   = $data['size'] ?? null;

                        return new Display($width, $height, $touch, $size);
                    };

                    $display = $fromArray((array) $data['display']);
                }

                return new Device(
                    architecture: Architecture::unknown,
                    deviceName: $deviceName,
                    marketingName: $marketingName,
                    manufacturer: $manufacturer,
                    brand: $brand,
                    type: $type,
                    display: $display,
                    dualOrientation: $dualOrientation,
                    simCount: null,
                    bits: Bits::unknown,
                );
            };

            $device = $fromArray($logger, (array) $data['device']);
        }

        $browser = null;

        if (array_key_exists('browser', $data)) {
            /**
             * @param LoggerInterface $logger
             * @param array                    $data
             *
             * @return BrowserInterface
             */
            $fromArray = function (LoggerInterface $logger, array $data): BrowserInterface {
                $name  = $data['name'] ?? null;
                $modus = $data['modus'] ?? null;
                $bits  = $data['bits'] ?? null;

                $type = null;

                if (array_key_exists('type', $data)) {
                    try {
                        $type = \UaBrowserType\Type::from($data['type']);
                    } catch (ValueError $e) {
                        $logger->info($e);
                    }
                }

                $version = null;

                if (array_key_exists('version', $data)) {
                    $version = (new VersionBuilder())->set($data['version']);
                }

                $manufacturer = null;

                if (array_key_exists('manufacturer', $data)) {
                    try {
                        $manufacturer = $this->companyLoader->load($data['manufacturer']);
                    } catch (NotFoundException $e) {
                        $logger->info($e);
                    }
                }

                return new Browser($name, $manufacturer, $version, $type, $bits, $modus);
            };
            $browser   = $fromArray($logger, (array) $data['browser']);
        }

        $os = null;

        if (array_key_exists('os', $data)) {
            /**
             * @param LoggerInterface $logger
             * @param array                    $data
             *
             * @return OsInterface
             */
            $fromArray = function (LoggerInterface $logger, array $data): OsInterface {
                $name          = $data['name'] ?? null;
                $marketingName = $data['marketingName'] ?? null;
                $bits          = $data['bits'] ?? null;

                $version = null;

                if (array_key_exists('version', $data)) {
                    $version = (new VersionBuilder())->set($data['version']);
                }

                $manufacturer = null;

                if (array_key_exists('manufacturer', $data)) {
                    try {
                        $manufacturer = $this->companyLoader->load($data['manufacturer']);
                    } catch (NotFoundException $e) {
                        $logger->info($e);
                    }
                }

                return new Os($name, $marketingName, $manufacturer, $version, $bits);
            };
            $os        = $fromArray($logger, (array) $data['os']);
        }

        $engine = null;

        if (array_key_exists('engine', $data)) {
            /**
             * @param LoggerInterface $logger
             * @param array                    $data
             *
             * @return EngineInterface
             */
            $fromArray = function (LoggerInterface $logger, array $data): EngineInterface {
                $name = $data['name'] ?? null;

                $version = null;

                if (array_key_exists('version', $data)) {
                    $version = (new VersionBuilder())->set($data['version']);
                }

                $manufacturer = null;

                if (array_key_exists('manufacturer', $data)) {
                    try {
                        $manufacturer = $this->companyLoader->load($data['manufacturer']);
                    } catch (NotFoundException $e) {
                        $logger->info($e);
                    }
                }

                return new Engine($name, $manufacturer, $version);
            };

            $engine = $fromArray($logger, (array) $data['engine']);
        }

        return new Result($headers, $device, $os, $browser, $engine);
    }
}
