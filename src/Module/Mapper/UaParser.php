<?php


namespace UaComparator\Module\Mapper;

use BrowserDetector\Version\Version;
use Psr\Cache\CacheItemPoolInterface;
use UaDataMapper\InputMapper;
use UaResult\Browser\Browser;
use UaResult\Device\Device;
use UaResult\Engine\Engine;
use UaResult\Os\Os;
use UaResult\Result\Result;
use Wurfl\Request\GenericRequestFactory;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 *
 * @category  UaComparator
 *
 * @author    Thomas Mueller <mimmi20@live.de>
 * @copyright 2015 Thomas Mueller
 * @license   http://www.opensource.org/licenses/MIT MIT License
 */
class UaParser implements MapperInterface
{
    /**
     * @var \UaDataMapper\InputMapper|null
     */
    private $mapper = null;

    /**
     * @var \Psr\Cache\CacheItemPoolInterface|null
     */
    private $cache = null;

    /**
     * @param \UaDataMapper\InputMapper         $mapper
     * @param \Psr\Cache\CacheItemPoolInterface $cache
     */
    public function __construct(InputMapper $mapper, CacheItemPoolInterface $cache)
    {
        $this->mapper = $mapper;
        $this->cache  = $cache;
    }

    /**
     * Gets the information about the browser by User Agent
     *
     * @param \stdClass $parserResult
     * @param string    $agent
     *
     * @return \UaResult\Result\Result the object containing the browsers details.
     */
    public function map($parserResult, $agent)
    {
        $browser = new Browser(
            $this->mapper->mapBrowserName($parserResult->ua->family),
            null,
            new Version((int) $parserResult->ua->major, (int) $parserResult->ua->minor, (string) $parserResult->ua->patch)
        );

        $os = new Os(
            $this->mapper->mapOsName($parserResult->os->family),
            null,
            null,
            new Version((int) $parserResult->os->major, (int) $parserResult->os->minor, (string) $parserResult->os->patch)
        );

        $device = new Device(null, null);
        $engine = new Engine(null);

        $requestFactory = new GenericRequestFactory();

        return new Result($requestFactory->createRequestForUserAgent($agent), $device, $os, $browser, $engine);
    }

    /**
     * @return null|\UaDataMapper\InputMapper
     */
    public function getMapper()
    {
        return $this->mapper;
    }
}
