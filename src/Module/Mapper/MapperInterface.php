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

use UaDataMapper\InputMapper;
use UaResult\Result\Result;

/**
 * Browscap.ini parsing class with caching and update capabilities
 */
interface MapperInterface
{
    /**
     * Gets the information about the browser by User Agent
     *
     * @return Result the object containing the browsers details
     *
     * @throws void
     */
    public function map(mixed $parserResult, string $agent): Result;

    /** @throws void */
    public function getMapper(): InputMapper | null;
}
