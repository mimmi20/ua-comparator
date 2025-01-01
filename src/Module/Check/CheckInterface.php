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

namespace UaComparator\Module\Check;

use GuzzleHttp\Psr7\Response;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use stdClass;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 */
interface CheckInterface
{
    /** @throws void */
    public function getResponse(
        Response $response,
        RequestInterface $request,
        CacheItemPoolInterface $cache,
        LoggerInterface $logger,
        string $agent,
    ): array | stdClass | null;
}
