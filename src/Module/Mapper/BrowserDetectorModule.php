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

use Psr\Cache\CacheItemPoolInterface;
use UaDataMapper\InputMapper;
use UaResult\Result\Result;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 */
final class BrowserDetectorModule implements MapperInterface
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
     * @param Result $parserResult
     *
     * @return Result the object containing the browsers details
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    public function map(mixed $parserResult, string $agent): Result
    {
        return $parserResult;
    }

    public function getMapper(): InputMapper | null
    {
        return $this->mapper;
    }
}
