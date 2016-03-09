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

/**
 * BrowserDetectorModule.ini parsing class with caching and update capabilities
 *
 * @category  BrowserDetectorModule
 *
 * @author    Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
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
        $wochen      = \bcdiv((int) $time, 604800, 0);
        $restwoche   = \bcmod((int) $time, 604800);
        $tage        = \bcdiv($restwoche, 86400, 0);
        $resttage    = \bcmod($restwoche, 86400);
        $stunden     = \bcdiv($resttage, 3600, 0);
        $reststunden = \bcmod($resttage, 3600);
        $minuten     = \bcdiv($reststunden, 60, 0);
        $sekunden    = \bcmod($reststunden, 60);

        return substr('00' . $wochen, -2) . ' Wochen '
        . substr('00' . $tage, -2) . ' Tage '
        . substr('00' . $stunden, -2) . ' Stunden '
        . substr('00' . $minuten, -2) . ' Minuten '
        . substr('00' . $sekunden, -2) . ' Sekunden';
    }
}
