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
use GuzzleHttp\Psr7\Response;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use UaResult\Result\ResultFactory;

use function html_entity_decode;
use function is_array;
use function mb_strpos;
use function unserialize;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 */
final class BrowserDetectorModule implements CheckInterface
{
    /**
     * @throws RequestException
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    public function getResponse(
        Response $response,
        RequestInterface $request,
        CacheItemPoolInterface $cache,
        LoggerInterface $logger,
        string $agent,
    ): array {
        /*
         * no json returned?
         */
        $contentType = $response->getHeader('Content-Type');

        if (!isset($contentType[0]) || $contentType[0] !== 'x-application/serialize') {
            throw new RequestException(
                'Could not get valid "x-application/serialize" response from "' . $request->getUri()
                . '". Response is "' . $response->getBody()->getContents() . '"',
                $request,
            );
        }

        $rawContent = $response->getBody()->getContents();

        if (mb_strpos((string) $rawContent, '<') !== false) {
            throw new RequestException(
                'An Error occured while calling "' . $request->getUri() . '". Response is "'
                . $rawContent . '"',
                $request,
            );
        }

        $content = @unserialize(html_entity_decode((string) $rawContent));

        if (!is_array($content) || !isset($content['result'])) {
            throw new RequestException(
                'Could not get valid response from "' . $request->getUri() . '". Response is "'
                . $rawContent . '"',
                $request,
            );
        }

        return (new ResultFactory())->fromArray($cache, $logger, $content['result']);
    }
}
