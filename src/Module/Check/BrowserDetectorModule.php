<?php


namespace UaComparator\Module\Check;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use UaResult\Result\ResultFactory;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 *
 * @category  UaComparator
 *
 * @author    Thomas Mueller <mimmi20@live.de>
 * @copyright 2015 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 */
class BrowserDetectorModule implements CheckInterface
{
    /**
     * @param \GuzzleHttp\Psr7\Response          $response
     * @param \Psr\Http\Message\RequestInterface $request
     * @param \Psr\Cache\CacheItemPoolInterface  $cache
     * @param \Psr\Log\LoggerInterface           $logger
     * @param string                             $agent
     *
     * @return array
     */
    public function getResponse(
        Response $response,
        RequestInterface $request,
        CacheItemPoolInterface $cache,
        LoggerInterface $logger,
        $agent
    ) {

        /*
         * no json returned?
         */
        $contentType = $response->getHeader('Content-Type');
        if (! isset($contentType[0]) || $contentType[0] !== 'x-application/serialize') {
            throw new RequestException(
                'Could not get valid "x-application/serialize" response from "' . $request->getUri()
                . '". Response is "' . $response->getBody()->getContents() . '"',
                $request
            );
        }

        $rawContent = $response->getBody()->getContents();

        if (false !== strpos($rawContent, '<')) {
            throw new RequestException(
                'An Error occured while calling "' . $request->getUri() . '". Response is "'
                . $rawContent . '"',
                $request
            );
        }

        $content = @unserialize(html_entity_decode($rawContent));

        if (! is_array($content) || ! isset($content['result'])) {
            throw new RequestException(
                'Could not get valid response from "' . $request->getUri() . '". Response is "'
                . $rawContent . '"',
                $request
            );
        }

        return (new ResultFactory())->fromArray($cache, $logger, $content['result']);
    }
}
