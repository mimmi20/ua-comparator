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

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

use function assert;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 */
final class Request
{
    /**
     * sends the request and checks the response code
     *
     * @throws RequestException
     * @throws GuzzleException
     */
    public function getResponse(RequestInterface $request, Client $client): Response
    {
        $response = $client->send($request);
        assert($response instanceof Response);

        if (200 !== $response->getStatusCode()) {
            throw new RequestException('Could not get valid response from "' . $request->getUri() . '". Status code is: "' . $response->getStatusCode() . '"', $request);
        }

        return $response;
    }
}
