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

/**
 * BrowserDetectorModule.ini parsing class with caching and update capabilities
 *
 * @category  BrowserDetectorModule
 * @package   BrowserDetectorModule
 * @author    Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 * @copyright 2012-2014 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 */
class LineHandler
{
    /**
     * @param string                                $agent
     * @param \UaComparator\Module\ModuleCollection $collection
     * @param \UaComparator\Helper\MessageFormatter $messageFormatter
     * @param integer                               $i
     * @param array                                 $checks
     *
     * @throws \Exception
     */
    public function handleLine(
        $agent,
        ModuleCollection $collection,
        MessageFormatter $messageFormatter,
        $i,
        array $checks = array()
    )
    {
        $startTime = microtime(true);
        $ok        = true;
        $matches   = array();
        $aLength   = ($collection->count() + 1) * (COL_LENGTH + 1);

        /***************************************************************************
         * handle modules
         */

        foreach ($collection as $module) {
            $module
                ->startTimer()
                ->detect($agent)
                ->endTimer()
            ;
        }

        /***************************************************************************
         * handle modules - end
         */

        /**
         * Auswertung
         */

        $content  = str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(' ', $collection->count() - 1) . '| ' . $agent . "\n";
        $content .= str_repeat('-', FIRST_COL_LENGTH) . '+' . str_repeat('-', $collection->count() - 1) . '+' . str_repeat('-', $aLength) . "\n";
        $content .= str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(' ', $collection->count() - 1) . '|' . str_repeat(' ', COL_LENGTH) . '|';
        foreach ($collection as $target) {
            $content .= str_pad($target->getName(), COL_LENGTH, ' ', STR_PAD_RIGHT) . '|';
        }
        $content .= "\n";

        $content .= str_pad($i, FIRST_COL_LENGTH, ' ', STR_PAD_LEFT) . '|' . str_repeat(' ', $collection->count() - 1) . '|' . str_repeat('-', COL_LENGTH) . '|';
        for ($i = 0, $count = $collection->count(); $i < $count; $i++) {
            $content .= str_repeat('-', COL_LENGTH) . '|';
        }
        $content .= "\n";

        $startString = '';

        foreach ($checks as $label => $x) {
            if (empty($x['key'])) {
                $key = $label;
            } else {
                $key = $x['key'];
            }

            if (empty($x['startString'])) {
                $startString = $key . ': ';
            } else {
                $startString = $x['startString'];
            }

            $returnMatches = array();
            $returnContent = '';
            $returnOk      = true;

            list($returnOk, $returnContent, $returnMatches) = $messageFormatter->formatMessage(
                $returnContent,
                $returnMatches,
                $label,
                $key,
                $startString,
                $returnOk
            );

            //if (!$returnOk) {
                $matches  = $matches + $returnMatches;
                $content .= $returnContent;
                $ok       = $ok && $returnOk;
            //}
        }

        if (!$ok) {
            $content = "\n" . str_repeat('-', FIRST_COL_LENGTH) . '+' . str_repeat('-', $collection->count() - 1) . '+' . str_repeat('-', $aLength) . "\n" . $content;

            $content .= str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(' ', $collection->count() - 1) . '|' . str_repeat('-', COL_LENGTH) . '|';
            for ($j = 0, $count = $collection->count(); $j < $count; $j++) {
                $content .= str_repeat('-', COL_LENGTH) . '|';
            }
            $content .= "\n";
            $content .= str_repeat(' ', FIRST_COL_LENGTH) . '|' . str_repeat(' ', $collection->count() - 1) . '|' . "\n";

            $fullTime = microtime(true) - $startTime;

            $content .= $startString . 'Time:' . "\n";
            foreach ($collection as $target) {
                $content .= $startString . '        Detection (' . $target->getName() . ')' . str_repeat(' ', 60 - strlen($target->getName())) . ':' . number_format($target->getTime(), 10, ',', '.') . ' Sek.' . "\n";
            }
            $content .= $startString . '        Complete                         :' . number_format($fullTime, 10, ',', '.') . ' Sek.' . "\n";
            $content .= $startString . '        Absolute TOTAL                   :' . TimeFormatter::formatTime(microtime(true) - START_TIME) . "\n";
        } else {
            $content = '';
        }

        if (in_array('-', $matches)) {
            $content .= '-';
        } elseif (in_array(':', $matches)) {
            $content .= ':';
        } else {
            $content .= '.';
        }

        if (($i % 100) == 0) {
            $content .= "\n";
        }

        if (in_array('-', $matches)) {
            throw new \Exception($content, 1);
        } elseif (in_array(':', $matches)) {
            throw new \Exception($content, 2);
        } else {
            throw new \Exception($content, 3);
        }
    }
}

