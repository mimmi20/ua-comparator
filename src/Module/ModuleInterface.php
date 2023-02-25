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

namespace UaComparator\Module;

use UaComparator\Module\Check\CheckInterface;
use UaComparator\Module\Mapper\MapperInterface;
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
    public function detect(string $agent): self;

    /**
     * starts the detection timer
     *
     * @throws void
     */
    public function startTimer(): self;

    /**
     * stops the detection timer
     *
     * @throws void
     */
    public function endTimer(): self;

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
    public function setName(string $name): self;

    /** @throws void */
    public function getConfig(): array | null;

    /** @throws void */
    public function setConfig(array $config): Http;

    /** @throws void */
    public function getCheck(): CheckInterface | null;

    /** @throws void */
    public function setCheck(CheckInterface $check): Http;

    /** @throws void */
    public function getMapper(): MapperInterface | null;

    /** @throws void */
    public function setMapper(MapperInterface $mapper): Http;

    /** @throws void */
    public function getDetectionResult(): Result | null;
}
