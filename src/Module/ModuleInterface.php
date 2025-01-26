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

namespace UaComparator\Module;

use UaResult\Result\Result;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 */
interface ModuleInterface
{
    /**
     * initializes the module
     *
     * @throws void
     */
    public function init(): self;

    /** @throws void */
    public function detect(string $agent, array $headers): self;

    /**
     * starts the detection timer
     *
     * @throws void
     */
    public function startBenchmark(): self;

    /**
     * stops the detection timer
     *
     * @throws void
     */
    public function endBenchmark(): self;

    /**
     * returns the needed time
     *
     * @throws void
     */
    public function getTime(): float;

    /**
     * returns the maximum needed memory
     *
     * @throws void
     */
    public function getMaxMemory(): int;

    /** @throws void */
    public function getName(): string;

    /** @throws void */
    public function getDetectionResult(): Result | null;
}
