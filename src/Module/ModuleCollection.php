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

use ArrayAccess;
use Countable;
use Iterator;

use function count;

/**
 * UaComparator.ini parsing class with caching and update capabilities
 */
final class ModuleCollection implements ArrayAccess, Countable, Iterator
{
    /** @var array<ModuleInterface> */
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

    /**
     * @return array<ModuleInterface>
     *
     * @throws void
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    /**
     * initializes the module
     *
     * @throws void
     */
    public function init(): self
    {
        foreach ($this->modules as $module) {
            $module->init();
        }

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

    /**
     * @return bool true on success or false on failure.
     *              </p>
     *              <p>
     *              The return value will be casted to boolean if non-boolean was returned.
     *
     * @throws void
     */
    public function offsetExists(int | string $offset): bool
    {
        return isset($this->modules[$offset]);
    }

    /**
     * @return mixed can return all value types
     *
     * @throws void
     */
    public function offsetGet(int | string $offset): mixed
    {
        return $this->modules[$offset] ?? null;
    }

    /** @throws void */
    public function offsetSet(int | string | null $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->modules[] = $value;
        } else {
            $this->modules[$offset] = $value;
        }
    }

    /** @throws void */
    public function offsetUnset(int | string $offset): void
    {
        unset($this->modules[$offset]);
    }
}
