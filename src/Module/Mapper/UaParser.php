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

namespace UaComparator\Module\Mapper;

use BrowserDetector\Version\Version;
use Psr\Cache\CacheItemPoolInterface;
use stdClass;
use UaDataMapper\InputMapper;
use UaResult\Browser\Browser;
use UaResult\Device\Device;
use UaResult\Engine\Engine;
use UaResult\Os\Os;
use UaResult\Result\Result;
use Wurfl\Request\GenericRequestFactory;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 */
final class UaParser implements MapperInterface
{
    private InputMapper | null $mapper = null;

    private CacheItemPoolInterface | null $cache = null;

    public function __construct(InputMapper $mapper, CacheItemPoolInterface $cache)
    {
        $this->mapper = $mapper;
        $this->cache  = $cache;
    }

    /**
     * Gets the information about the browser by User Agent
     *
     * @return Result the object containing the browsers details
     */
    public function map(stdClass $parserResult, string $agent): Result
    {
        $browser = new Browser(
            $this->mapper->mapBrowserName($parserResult->ua->family),
            null,
            new Version((int) $parserResult->ua->major, (int) $parserResult->ua->minor, (string) $parserResult->ua->patch),
        );

        $os = new Os(
            $this->mapper->mapOsName($parserResult->os->family),
            null,
            null,
            new Version((int) $parserResult->os->major, (int) $parserResult->os->minor, (string) $parserResult->os->patch),
        );

        $device = new Device(null, null);
        $engine = new Engine(null);

        $requestFactory = new GenericRequestFactory();

        return new Result($requestFactory->createRequestForUserAgent($agent), $device, $os, $browser, $engine);
    }

    public function getMapper(): InputMapper | null
    {
        return $this->mapper;
    }
}
