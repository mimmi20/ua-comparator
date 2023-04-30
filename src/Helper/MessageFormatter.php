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

namespace UaComparator\Helper;

use BrowserDetector\Version\Version;
use BrowserDetector\Version\VersionInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use UaResult\Result\Result;
use UaResult\Result\ResultFactory;

use function array_keys;
use function assert;
use function in_array;
use function mb_strlen;
use function mb_strpos;
use function mb_strtolower;
use function mb_substr;
use function str_pad;

/**
 * BrowserDetectorModule.ini parsing class with caching and update capabilities
 */
final class MessageFormatter
{
    /** @var array<Result> */
    private array $collection;
    private int $columnsLength = 0;
    private readonly ResultFactory $resultFactory;

    /** @throws void */
    public function __construct()
    {
        $this->resultFactory = new ResultFactory();
    }

    /**
     * @param array<Result> $collection
     *
     * @throws void
     */
    public function setCollection(array $collection): self
    {
        $this->collection = $collection;

        return $this;
    }

    /** @throws void */
    public function setColumnsLength(int $columnsLength): self
    {
        $this->columnsLength = $columnsLength;

        return $this;
    }

    /**
     * @return array<string>
     *
     * @throws void
     */
    public function formatMessage(string $propertyName, CacheItemPoolInterface $cache, LoggerInterface $logger): array
    {
        $modules      = array_keys($this->collection);
        $firstElement = $this->collection[$modules[0]]['result'];
        assert($firstElement instanceof Result);

        $strReality = $firstElement === null ? '(NULL)' : $this->getValue(
            $this->resultFactory->fromArray($cache, $logger, (array) $firstElement),
            $propertyName,
        );

        $detectionResults = [];

        foreach ($modules as $module => $name) {
            $element = $this->collection[$name]['result'];
            assert($element instanceof Result);

            $strTarget = $element === null ? '(NULL)' : $this->getValue(
                $this->resultFactory->fromArray($cache, $logger, (array) $element),
                $propertyName,
            );

            if (mb_strtolower($strTarget) === mb_strtolower($strReality)) {
                $r1 = ' ';
            } elseif (
                in_array($strReality, ['(NULL)', '', '(empty)'], true)
                || in_array($strTarget, ['(NULL)', '', '(empty)'], true)
            ) {
                $r1 = ' ';
            } else {
                if (
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
            }

            $result = $r1 . $strTarget;

            if (mb_strlen($result) > $this->columnsLength) {
                $result = mb_substr($result, 0, $this->columnsLength - 3) . '...';
            }

            $detectionResults[$module] = str_pad($result, $this->columnsLength, ' ');
        }

        return $detectionResults;
    }

    /** @throws void */
    private function getValue(Result $element, string $propertyName): string
    {
        switch ($propertyName) {
            case 'mobile_browser':
                $value = $element->getBrowser()->getName();

                break;
            case 'mobile_browser_version':
                $value = $element->getBrowser()->getVersion();

                if ($value instanceof Version) {
                    $value = $value->getVersion(
                        VersionInterface::IGNORE_MICRO_IF_EMPTY | VersionInterface::IGNORE_MINOR_IF_EMPTY | VersionInterface::IGNORE_MACRO_IF_EMPTY,
                    );
                }

                if ($value === '') {
                    $value = null;
                }

                break;
            case 'mobile_browser_modus':
                $value = $element->getBrowser()->getModus();

                break;
            case 'mobile_browser_bits':
                $value = $element->getBrowser()->getBits();

                break;
            case 'browser_type':
                $value = $element->getBrowser()->getType()->getName();

                break;
            case 'mobile_browser_manufacturer':
                $value = $element->getBrowser()->getManufacturer()->getName();

                break;
            case 'renderingengine_name':
                $value = $element->getEngine()->getName();

                break;
            case 'renderingengine_version':
                $value = $element->getEngine()->getVersion();

                if ($value instanceof Version) {
                    $value = $value->getVersion(
                        VersionInterface::IGNORE_MICRO_IF_EMPTY | VersionInterface::IGNORE_MINOR_IF_EMPTY | VersionInterface::IGNORE_MACRO_IF_EMPTY,
                    );
                }

                if ($value === '') {
                    $value = null;
                }

                break;
            case 'renderingengine_manufacturer':
                $value = $element->getEngine()->getManufacturer()->getName();

                break;
            case 'device_os':
                $value = $element->getOs()->getName();

                break;
            case 'device_os_version':
                $value = $element->getOs()->getVersion();

                if ($value instanceof Version) {
                    $value = $value->getVersion(
                        VersionInterface::IGNORE_MICRO_IF_EMPTY | VersionInterface::IGNORE_MINOR_IF_EMPTY,
                    );
                }

                if ($value === '') {
                    $value = null;
                }

                break;
            case 'device_os_bits':
                $value = $element->getOs()->getBits();

                break;
            case 'device_os_manufacturer':
                $value = $element->getOs()->getManufacturer()->getName();

                break;
            case 'brand_name':
                $value = $element->getDevice()->getBrand()->getBrandName();

                break;
            case 'marketing_name':
                $value = $element->getDevice()->getMarketingName();

                break;
            case 'model_name':
                $value = $element->getDevice()->getDeviceName();

                break;
            case 'manufacturer_name':
                $value = $element->getDevice()->getManufacturer()->getName();

                break;
            case 'device_type':
                $value = $element->getDevice()->getType()->getName();

                break;
            case 'pointing_method':
                $value = $element->getDevice()->getPointingMethod();

                break;
            case 'has_qwerty_keyboard':
                $value = $element->getDevice()->getHasQwertyKeyboard();

                break;
            case 'resolution_width':
                $value = $element->getDevice()->getResolutionWidth();

                break;
            case 'resolution_height':
                $value = $element->getDevice()->getResolutionHeight();

                break;
            case 'dual_orientation':
                $value = $element->getDevice()->getDualOrientation();

                break;
            case 'colors':
                $value = $element->getDevice()->getColors();

                break;
            default:
                $value = '(n/a)';

                break;
        }

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
}
