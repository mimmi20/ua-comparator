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

use GuzzleHttp\Exception\RequestException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use stdClass;

use function html_entity_decode;
use function json_decode;
use function mb_strpos;

use const JSON_THROW_ON_ERROR;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 */
final class Local implements CheckInterface
{
    /**
     * @throws RequestException
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    public function getResponse(
        ResponseInterface $response,
        RequestInterface $request,
        CacheItemPoolInterface $cache,
        LoggerInterface $logger,
        string $agent,
    ): stdClass {
        /*
         * no json returned?
         */
        $contentType = $response->getHeader('Content-Type');

        if (!isset($contentType[0]) || $contentType[0] !== 'application/json') {
            throw new RequestException(
                'Could not get valid "application/json" response from "' . $request->getUri() . '". Response is "' . $response->getBody()->getContents() . '"',
                $request,
            );
        }

        $rawContent = $response->getBody()->getContents();

        if (mb_strpos((string) $rawContent, '<') !== false) {
            throw new RequestException(
                'An Error occured while calling "' . $request->getUri() . '". Response is "' . $response->getBody()->getContents() . '"',
                $request,
            );
        }

        $content = json_decode(
            html_entity_decode((string) $rawContent),
            null,
            512,
            JSON_THROW_ON_ERROR,
        );

        if (!$content instanceof stdClass || !isset($content->result)) {
            throw new RequestException(
                'Could not get valid response from "' . $request->getUri() . '". Response is "' . $response->getBody()->getContents() . '"',
                $request,
            );
        }

        return $content;
    }
}
