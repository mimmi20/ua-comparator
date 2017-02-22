<?php
/**
 * Copyright (c) 2015-2017, Thomas Mueller <mimmi20@live.de>
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
 * @category  UaComparator
 *
 * @author    Thomas Mueller <mimmi20@live.de>
 * @copyright 2015-2017 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 *
 * @link      https://github.com/mimmi20/ua-comparator
 */

namespace UaComparator\Helper;

/**
 * BrowserDetectorModule.ini parsing class with caching and update capabilities
 *
 * @category  BrowserDetectorModule
 *
 * @author    Thomas Mueller <mimmi20@live.de>
 * @copyright 2012-2014 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 */
class TimeFormatter
{
    /**
     * @param string $time
     *
     * @return string
     */
    public static function formatTime($time)
    {
        $wochen      = (int) ((int) $time / 604800);
        $restwoche   = (int) ((int) $time % 604800);
        $tage        = (int) ($restwoche / 86400);
        $resttage    = (int) ($restwoche % 86400);
        $stunden     = (int) ($resttage / 3600);
        $reststunden = (int) ($resttage % 3600);
        $minuten     = (int) ($reststunden / 60);
        $sekunden    = (int) ($reststunden % 60);

        return substr('00' . $wochen, -2) . ' Wochen '
            . substr('00' . $tage, -2) . ' Tage '
            . substr('00' . $stunden, -2) . ' Stunden '
            . substr('00' . $minuten, -2) . ' Minuten '
            . substr('00' . $sekunden, -2) . ' Sekunden';
    }
}
