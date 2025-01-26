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

namespace UaComparator\Helper;

use BrowserDetector\Version\VersionInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use UaResult\Result\Result;
use UaResult\Result\ResultFactory;
use UnexpectedValueException;

use function array_keys;
use function assert;
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
    private ResultFactory $resultFactory;

    /**
     * @param array<Result> $collection
     *
     * @throws void
     */
    public function __construct(private array $collection, private int $columnsLength)
    {
        $this->resultFactory = new ResultFactory();
    }

    /**
     * @return array<string>
     *
     * @throws UnexpectedValueException
     */
    public function formatMessage(string $propertyName, CacheItemPoolInterface $cache, LoggerInterface $logger): array
    {
        $modules      = array_keys($this->collection);
        $firstElement = $this->collection[$modules[0]];
        assert($firstElement instanceof Result);

        $strReality = $firstElement === null ? '(NULL)' : $this->getValue(
            $this->resultFactory->fromArray($cache, $logger, (array) $firstElement),
            $propertyName,
        );

        $detectionResults = [];

        foreach ($modules as $module => $name) {
            $element = $this->collection[$name];
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
}
