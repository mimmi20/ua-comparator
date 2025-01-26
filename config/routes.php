<?php
/**
 * This file is part of the mimmi20/mezzio-sample-project package.
 *
 * Copyright (c) 2021, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

/**
 * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
 */
return static function (FrameworkX\App $app): void {
    $app->get('/matomo/', \UaComparator\Handler\MatomoHandler::class);
    $app->get('/browser-detector/', \UaComparator\Handler\BrowserDetectorHandler::class);
    $app->get('/browscap-php/', \UaComparator\Handler\BrowscapPhpHandler::class);
    $app->get('/cbschuld/', \UaComparator\Handler\CbschuldHandler::class);
    $app->get('/crawler-detect/', \UaComparator\Handler\CrawlerDetectHandler::class);
    $app->get('/donatj/', \UaComparator\Handler\DonatjHandler::class);
    $app->get('/endorphin/', \UaComparator\Handler\EndorphinHandler::class);
};
