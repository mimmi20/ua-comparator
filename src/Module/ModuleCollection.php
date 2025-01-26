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

use Countable;
use Iterator;

use function count;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 *
 * @implements Iterator<int, ModuleInterface>
 */
final class ModuleCollection implements Countable, Iterator
{
    /** @var array<int, ModuleInterface> */
    private array $modules = [];
    private int $position  = 0;

    /** @throws void */
    public function __construct()
    {
        $this->position = 0;
    }

    /** @throws void */
    public function addModule(ModuleInterface $module): self
    {
        $this->modules[] = $module;

        return $this;
    }

    /** @throws void */
    public function current(): ModuleInterface
    {
        return $this->modules[$this->position];
    }

    /** @throws void */
    public function next(): void
    {
        ++$this->position;
    }

    /**
     * @return int scalar on success, or null on failure
     *
     * @throws void
     */
    public function key(): int
    {
        return $this->position;
    }

    /**
     * @return bool The return value will be casted to boolean and then evaluated.
     *              Returns true on success or false on failure.
     *
     * @throws void
     */
    public function valid(): bool
    {
        return isset($this->modules[$this->position]);
    }

    /** @throws void */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /** @throws void */
    public function count(): int
    {
        return count($this->modules);
    }
}
