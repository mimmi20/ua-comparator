<?php
/**
 * Copyright (c) 2015, Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
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
 * @author    Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 * @copyright 2015 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 *
 * @link      https://github.com/mimmi20/ua-comparator
 */

namespace UaComparator\Module\Check;

use DeviceDetector\Parser\Client\Browser;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use UaResult\Result;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 *
 * @category  UaComparator
 *
 * @author    Thomas Mueller <t_mueller_stolzenhain@yahoo.de>
 * @copyright 2015 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 */
class WhatIsMyBrowserCom implements CheckInterface
{
    /**
     * @param \GuzzleHttp\Psr7\Response          $response
     * @param \Psr\Http\Message\RequestInterface $request
     * @param string                             $agent
     *
     * @throws \GuzzleHttp\Exception\RequestException
     * @return \stdClass
     */
    public function getResponse(Response $response, RequestInterface $request, $agent)
    {
        /*
         * no json returned?
         */
        $contentType = $response->getHeader('Content-Type');

        if (! isset($contentType[0]) || $contentType[0] !== 'application/json') {
            throw new RequestException('Could not get valid "application/json" response from "' . $request->getUri() . '". Response is "' . $response->getBody()->getContents() . '"', $request);
        }

        $content = json_decode($response->getBody()->getContents());

        /*
         * No result
         */
        if (isset($content->message_code) && $content->message_code === 'no_user_agent') {
            throw new RequestException('No result found for user agent: ' . $agent, $request);
        }

        /*
         * Limit exceeded
         */
        if (isset($content->message_code) && $content->message_code === 'usage_limit_exceeded') {
            throw new RequestException('Exceeded the maximum number of request for WhatIsMyBrowser-API', $request);
        }

        /*
         * Error
         */
        if (isset($content->message_code) && $content->message_code === 'no_api_user_key') {
            throw new RequestException('Missing API key for WhatIsMyBrowser-API', $request);
        }

        if (isset($content->message_code) && $content->message_code === 'user_key_invalid') {
            throw new RequestException('Your API key is not valid', $request);
        }

        if (!isset($content->result) || $content->result !== 'success') {
            throw new RequestException('Could not get valid response from "' . $request->getUri() . '". Response is "' . $response->getBody()->getContents() . '"', $request);
        }

        /*
         * Missing data?
         */
        if (! $content instanceof \stdClass || ! isset($content->parse) || ! $content->parse instanceof \stdClass) {
            throw new RequestException('Could not get valid response from "' . $request->getUri() . '". Response is "' . print_r($content, true) . '"', $request);
        }

        return $content->parse;
    }
}
