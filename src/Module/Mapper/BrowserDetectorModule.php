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

namespace UaComparator\Module\Mapper;

use UaDataMapper\InputMapper;
use UaResult\Result\Result;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 */
final readonly class BrowserDetectorModule implements MapperInterface
{
    /** @throws void */
    public function __construct(private InputMapper | null $mapper)
    {
    }

    /**
     * Gets the information about the browser by User Agent
     *
     * @param Result $parserResult
     *
     * @return Result the object containing the browsers details
     *
     * @throws void
     *
     * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
     */
    public function map(mixed $parserResult, string $agent): Result
    {
        return $parserResult;
    }

    /** @throws void */
    public function getMapper(): InputMapper | null
    {
        return $this->mapper;
    }
}
