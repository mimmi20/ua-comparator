<?php
/**
 * Copyright (c) 2012-2014, Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
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
 * @category  BrowserDetectorModule
 *
 * @author    Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 * @copyright 2012-2014 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 *
 * @link      https://github.com/mimmi20/BrowserDetectorModule
 */

namespace UaComparator\Helper;

use BrowserDetector\Version\Version;
use BrowserDetector\Version\VersionInterface;
use UaResult\Result\Result;

/**
 * BrowserDetectorModule.ini parsing class with caching and update capabilities
 *
 * @category  BrowserDetectorModule
 *
 * @author    Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 * @copyright 2012-2014 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 */
class MessageFormatter
{
    /**
     * @var \UaResult\Result\Result[]
     */
    private $collection = null;

    /**
     * @var int
     */
    private $columnsLength = 0;

    /**
     * @param \UaResult\Result\Result[] $collection
     *
     * @return \UaComparator\Helper\MessageFormatter
     */
    public function setCollection(array $collection)
    {
        $this->collection = $collection;

        return $this;
    }

    /**
     * @param int $columnsLength
     *
     * @return \UaComparator\Helper\MessageFormatter
     */
    public function setColumnsLength($columnsLength)
    {
        $this->columnsLength = $columnsLength;

        return $this;
    }

    /**
     * @param string $propertyName
     *
     * @return string[]
     */
    public function formatMessage($propertyName)
    {
        $modules      = array_keys($this->collection);
        /** @var \UaResult\Result\Result $firstElement */
        $firstElement = $this->collection[$modules[0]]['result'];

        if (null === $firstElement) {
            $strReality = '(NULL)';
        } else {
            $strReality   = $this->getValue($firstElement, $propertyName);
        }

        $detectionResults = [];

        foreach ($modules as $module => $name) {
            /** @var \UaResult\Result\Result $element */
            $element   = $this->collection[$name]['result'];
            if (null === $element) {
                $strTarget = '(NULL)';
            } else {
                $strTarget = $this->getValue($element, $propertyName);
            }

            if (strtolower($strTarget) === strtolower($strReality)) {
                $r1 = ' ';
            } elseif (in_array($strReality, ['(NULL)', '', '(empty)']) || in_array($strTarget, ['(NULL)', '', '(empty)'])) {
                $r1 = ' ';
            } else {
                if ((strlen($strTarget) > strlen($strReality))
                    && (0 < strlen($strReality))
                    && (0 === strpos($strTarget, $strReality))
                ) {
                    $r1 = '-';
                } elseif ((strlen($strTarget) < strlen($strReality))
                    && (0 < strlen($strTarget))
                    && (0 === strpos($strReality, $strTarget))
                ) {
                    $r1 = ' ';
                } else {
                    $r1 = '-';
                }
            }

            $result = $r1 . $strTarget;
            if (strlen($result) > $this->columnsLength) {
                $result = substr($result, 0, $this->columnsLength - 3) . '...';
            }

            $detectionResults[$module] = str_pad($result, $this->columnsLength, ' ');
        }

        return $detectionResults;
    }

    /**
     * @param \UaResult\Result\Result $element
     * @param string                  $propertyName
     *
     * @return string
     */
    private function getValue(Result $element, $propertyName)
    {
        switch ($propertyName) {
            case 'wurflKey':
                $value = $element->getWurflKey();
                break;
            case 'mobile_browser':
                $value = $element->getBrowser()->getName();
                break;
            case 'mobile_browser_version':
                $value = $element->getBrowser()->getVersion();

                if ($value instanceof Version) {
                    $value = $value->getVersion(VersionInterface::IGNORE_MICRO_IF_EMPTY | VersionInterface::IGNORE_MINOR_IF_EMPTY | VersionInterface::IGNORE_MACRO_IF_EMPTY);
                }

                if ('' === $value) {
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
                $value = $element->getBrowser()->getType();
                break;
            case 'mobile_browser_manufacturer':
                $value = $element->getBrowser()->getManufacturer();
                break;
            case 'renderingengine_name':
                $value = $element->getEngine()->getName();
                break;
            case 'renderingengine_version':
                $value = $element->getEngine()->getVersion();

                if ($value instanceof Version) {
                    $value = $value->getVersion(VersionInterface::IGNORE_MICRO_IF_EMPTY | VersionInterface::IGNORE_MINOR_IF_EMPTY | VersionInterface::IGNORE_MACRO_IF_EMPTY);
                }

                if ('' === $value) {
                    $value = null;
                }
                break;
            case 'renderingengine_manufacturer':
                $value = $element->getEngine()->getManufacturer();
                break;
            case 'device_os':
                $value = $element->getOs()->getName();
                break;
            case 'device_os_version':
                $value = $element->getOs()->getVersion();

                if ($value instanceof Version) {
                    $value = $value->getVersion(VersionInterface::IGNORE_MICRO_IF_EMPTY | VersionInterface::IGNORE_MINOR_IF_EMPTY | VersionInterface::IGNORE_MACRO_IF_EMPTY);
                }

                if ('' === $value) {
                    $value = null;
                }
                break;
            case 'device_os_bits':
                $value = $element->getOs()->getBits();
                break;
            case 'device_os_manufacturer':
                $value = $element->getOs()->getManufacturer();
                break;
            case 'brand_name':
                $value = $element->getDevice()->getBrand();
                break;
            case 'marketing_name':
                $value = $element->getDevice()->getMarketingName();
                break;
            case 'model_name':
                $value = $element->getDevice()->getDeviceName();
                break;
            case 'manufacturer_name':
                $value = $element->getDevice()->getManufacturer();
                break;
            case 'device_type':
                $value = $element->getDevice()->getType();
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

        if (null === $value || 'null' === $value) {
            $output = '(NULL)';
        } elseif ('' === $value) {
            $output = '(empty)';
        } elseif (false === $value || 'false' === $value) {
            $output = '(false)';
        } elseif (true === $value || 'true' === $value) {
            $output = '(true)';
        } else {
            $output = (string) $value;
        }

        return $output;
    }
}
