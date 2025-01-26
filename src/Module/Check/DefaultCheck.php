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
use JsonException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use stdClass;

use function html_entity_decode;
use function json_decode;
use function mb_strpos;

use const JSON_THROW_ON_ERROR;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 */
final class DefaultCheck implements CheckInterface
{
    /**
     * @throws RequestException
     * @throws RuntimeException
     * @throws JsonException
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    public function getResponse(
        ResponseInterface $response,
        UriInterface $uri,
        CacheItemPoolInterface $cache,
        LoggerInterface $logger,
        string $agent,
    ): stdClass {
        /*
         * no json returned?
         */
        $contentType = $response->getHeader('Content-Type');

        if (!isset($contentType[0]) || $contentType[0] !== 'application/json') {
            throw new RuntimeException(
                'Could not get valid "application/json" response from "' . $uri . '". Response is "' . $response->getBody()->getContents() . '"',
            );
        }

        $rawContent = $response->getBody()->getContents();

        if (mb_strpos((string) $rawContent, '<') !== false) {
            throw new RuntimeException(
                'An Error occured while calling "' . $uri . '". Response is "' . $response->getBody()->getContents() . '"',
            );
        }

        $content = json_decode(
            html_entity_decode((string) $rawContent),
            null,
            512,
            JSON_THROW_ON_ERROR,
        );

        if (!$content instanceof stdClass || !isset($content->result)) {
            throw new RuntimeException(
                'Could not get valid response from "' . $uri . '". Response is "' . $response->getBody()->getContents() . '"',
            );
        }

        return $content;
    }
}
