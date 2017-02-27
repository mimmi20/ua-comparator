<?php
/**
 * This file is part of the ua-comparator package.
 *
 * Copyright (c) 2015-2017, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);
namespace UaComparator\Helper;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\RequestInterface;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 *
 * @category  UaComparator
 *
 * @author    Thomas Mueller <mimmi20@live.de>
 * @copyright 2015 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 */
class Request
{
    /**
     * sends the request and checks the response code
     *
     * @param \Psr\Http\Message\RequestInterface $request
     * @param \GuzzleHttp\Client                 $client
     *
     * @throws \GuzzleHttp\Exception\RequestException
     *
     * @return \GuzzleHttp\Psr7\Response
     */
    public function getResponse(RequestInterface $request, Client $client)
    {
        /* @var $response \GuzzleHttp\Psr7\Response */
        $response = $client->send($request);

        if ($response->getStatusCode() !== 200) {
            throw new RequestException('Could not get valid response from "' . $request->getUri() . '". Status code is: "' . $response->getStatusCode() . '"', $request);
        }

        return $response;
    }
}
