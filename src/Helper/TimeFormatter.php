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

use function mb_substr;

/**
 * BrowserDetectorModule.ini parsing class with caching and update capabilities
 */
final class TimeFormatter
{
    /** @throws void */
    public static function formatTime(string $time): string
    {
        $wochen      = (int) ((int) $time / 604800);
        $restwoche   = (int) $time % 604800;
        $tage        = (int) ($restwoche / 86400);
        $resttage    = $restwoche % 86400;
        $stunden     = (int) ($resttage / 3600);
        $reststunden = $resttage % 3600;
        $minuten     = (int) ($reststunden / 60);
        $sekunden    = $reststunden % 60;

        return mb_substr('00' . $wochen, -2) . ' Wochen '
            . mb_substr('00' . $tage, -2) . ' Tage '
            . mb_substr('00' . $stunden, -2) . ' Stunden '
            . mb_substr('00' . $minuten, -2) . ' Minuten '
            . mb_substr('00' . $sekunden, -2) . ' Sekunden';
    }
}
