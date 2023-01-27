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

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use UaComparator\Module\Check\CheckInterface;
use UaComparator\Module\Mapper\MapperInterface;
use UaResult\Result\Result;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 */
interface ModuleInterface
{
    /**
     * creates the module
     */
    public function __construct(LoggerInterface $logger, CacheItemPoolInterface $cache);

    /**
     * initializes the module
     */
    public function init(): self;

    public function detect(string $agent): self;

    /**
     * starts the detection timer
     */
    public function startTimer(): self;

    /**
     * stops the detection timer
     */
    public function endTimer(): self;

    /**
     * returns the needed time
     */
    public function getTime(): float;

    /**
     * returns the maximum needed memory
     */
    public function getMaxMemory(): int;

    public function getName(): string;

    public function setName(string $name): self;

    public function getConfig(): array | null;

    public function setConfig(array $config): Http;

    public function getCheck(): CheckInterface | null;

    public function setCheck(CheckInterface $check): Http;

    public function getMapper(): MapperInterface | null;

    public function setMapper(MapperInterface $mapper): Http;

    public function getDetectionResult(): Result | null;
}
