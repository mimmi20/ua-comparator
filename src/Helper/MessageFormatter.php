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
 * @package   BrowserDetectorModule
 * @author    Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 * @copyright 2012-2014 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 * @link      https://github.com/mimmi20/BrowserDetectorModule
 */

namespace UaComparator\Helper;

use UaComparator\Module\ModuleCollection;
use BrowserDetector\Detector\Version;

/**
 * BrowserDetectorModule.ini parsing class with caching and update capabilities
 *
 * @category  BrowserDetectorModule
 * @package   BrowserDetectorModule
 * @author    Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 * @copyright 2012-2014 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 */
class MessageFormatter
{
    /**
     * @var \UaComparator\Module\ModuleCollection
     */
    private $collection = null;

    /**
     * @var int
     */
    private $columnsLength = 0;

    /**
     * @param \UaComparator\Module\ModuleCollection $collection
     *
     * @return \UaComparator\Helper\MessageFormatter
     */
    public function setCollection(ModuleCollection $collection)
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
     * @param string       $content
     * @param array        $matches
     * @param string       $propertyTitel
     * @param string|array $propertyName
     * @param string       $start
     * @param bool         $ok
     *
     * @return array
     */
    public function formatMessage($content, $matches, $propertyTitel, $propertyName, $start = '', $ok = false)
    {
        static $allErrors = array();

        $mismatch      = false;
        $passed        = true;
        $testresult    = '|';
        $prefix        = ' ';
        $propertyTitel = trim($propertyTitel);

        if (is_string($propertyName)) {
            $reality = $this->collection[0]->getDetectionResult()->getCapability($propertyName);
        } elseif (is_array($propertyName) && is_callable(array($this->collection[0]->getDetectionResult(), $propertyName[0]))) {
            $reality = call_user_func_array(
                array($this->collection[0]->getDetectionResult(), $propertyName[0]),
                isset($propertyName[1]) ? $propertyName[1] : array()
            );
        } else {
            $reality = '(n/a)';
        }

        $start = substr($start, 0, -1 * (1 + $this->collection->count()));

        if (null === $reality || 'null' === $reality) {
            $strReality = '(NULL)';
        } elseif ('' === $reality) {
            $strReality = '(empty)';
        } elseif (false === $reality || 'false' === $reality) {
            $strReality = '(false)';
        } elseif (true === $reality || 'true' === $reality) {
            $strReality = '(true)';
        } else {
            $strReality = (string) $reality;
        }

        $detectionMessage = array(0 => str_pad($prefix . $strReality, $this->columnsLength, ' ') . '|');
        $fullname         = $this->collection[0]->getDetectionResult()->getFullBrowser(true, Version::MAJORMINOR);

        foreach (array_keys($this->collection->getModules()) as $id) {
            if (0 === $id) {
                continue;
            }

            if (is_string($propertyName)) {
                $target = $this->collection[$id]->getDetectionResult()->getCapability($propertyName);
            } elseif (is_array($propertyName) && is_callable(array($this->collection[$id]->getDetectionResult(), $propertyName[0]))) {
                $target = call_user_func_array(
                    array($this->collection[$id]->getDetectionResult(), $propertyName[0]),
                    isset($propertyName[1]) ? $propertyName[1] : array()
                );
            } else {
                $target = '(n/a)';
            }

            if ($target instanceof Version) {
                $target = $target->getVersion(Version::MAJORMINOR);
            }

            if (null === $target || 'null' === $target) {
                $strTarget = '(NULL)';
            } elseif ('' === $target) {
                $strTarget = '(empty)';
            } elseif (false === $target || 'false' === $target) {
                $strTarget = '(false)';
            } elseif (true === $target || 'true' === $target) {
                $strTarget = '(true)';
            } else {
                $strTarget = (string) $target;
            }

            if (strtolower($strTarget) === strtolower($strReality)) {
                $r  = ' ';
                $r1 = '+';
            } elseif (((null === $reality) || ('' === $reality) || ('' === $strReality)) && ((null === $target) || ('' === $target))) {
                $r  = ' ';
                $r1 = '?';
            } elseif ((null === $target) || ('' === $target) || ('' === $strTarget)) {
                $r  = ' ';
                $r1 = '%';
            } else {
                $mismatch = true;

                if ((strlen($strTarget) > strlen($strReality))
                    && (0 < strlen($strReality))
                    && (0 === strpos($strTarget, $strReality))
                ) {
                    $passed = false;
                    $r      = '-';
                    $r1     = '<';
                } elseif ((strlen($strTarget) < strlen($strReality))
                    && (0 < strlen($strTarget))
                    && (0 === strpos($strReality, $strTarget))
                ) {
                    $r  = ' ';
                    $r1 = '>';
                } elseif (isset($allErrors[$fullname][$propertyTitel])) {
                    $r      = ':';
                    $r1     = ':';
                } else {
                    $passed = false;
                    $r      = '-';
                    $r1     = '-';
                }
            }

            $testresult .= $r;
            $matches[]   = $r1;

            if (!isset($allErrors[$fullname][$propertyTitel])
                && $mismatch
                && !$passed
            ) {
                $allErrors[$fullname][$propertyTitel] = $reality;
            }

            $detectionMessage[] = str_pad($r1 . $strTarget, $this->columnsLength, ' ') . '|';
        }

        $prefix = ' ';

        $detectionMessage[0] = str_pad($prefix . $strReality, $this->columnsLength, ' ') . '|';

        $content .= $start . $testresult . '|' . substr(str_repeat(' ', $this->columnsLength)
                . $propertyTitel, -1 * $this->columnsLength) . '|' . implode('', $detectionMessage)
            . "\n";

        return array(($passed && $ok), $content, $matches);
    }
}

