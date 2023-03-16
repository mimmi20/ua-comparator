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

use function in_array;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 */
final class Woothee implements MapperInterface
{
    /** @throws void */
    public function __construct(
        private readonly InputMapper | null $mapper,
        private readonly CacheItemPoolInterface | null $cache,
    ) {
    }

    /**
     * Gets the information about the browser by User Agent
     *
     * @param stdClass $parserResult
     *
     * @return Result the object containing the browsers details
     *
     * @throws void
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
     */
    public function map($parserResult, string $agent): Result
    {
        $browserName = $this->mapper->mapBrowserName($parserResult->name);

        $browser = new Browser(
            $browserName,
            null,
            $this->mapper->mapBrowserVersion($parserResult->version, $browserName),
            $this->mapper->mapBrowserType($this->cache, $parserResult->category),
        );

        if (!empty($parserResult->os) && !in_array($parserResult->os, ['iPad', 'iPhone'], true)) {
            $osName    = $this->mapper->mapOsName($parserResult->os);
            $osVersion = $this->mapper->mapOsVersion($parserResult->os_version, $osName);

            if (!$osVersion instanceof Version) {
                $osVersion = null;
            }

            $os = new Os($osName, null, null, $osVersion);
        } else {
            $os = new Os(null, null);
        }

        $device = new Device(null, null);
        $engine = new Engine(null);

        $requestFactory = new GenericRequestFactory();

        return new Result($requestFactory->createRequestForUserAgent($agent), $device, $os, $browser, $engine);
    }

    /** @throws void */
    public function getMapper(): InputMapper | null
    {
        return $this->mapper;
    }
}
