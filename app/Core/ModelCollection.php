<?php

namespace App\Core;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;

/**
 * Lightweight collection wrapper for model lists.
 * Behaves like an array (supports foreach, [] access, count())
 * while also offering helper methods like ->count(), ->first(), ->map().
 */
class ModelCollection implements IteratorAggregate, Countable, JsonSerializable, \ArrayAccess
{
    /**
     * @var array<int, mixed>
     */
    protected array $items = [];

    public function __construct(array $items = [])
    {
        $this->items = array_values($items);
    }

    /**
     * Return the first item in the collection.
     */
    public function first()
    {
        return $this->items[0] ?? null;
    }

    /**
     * Number of items.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * ArrayAccess implementation.
     */
    public function offsetExists($offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset($offset): void
    {
        unset($this->items[$offset]);
    }

    /**
     * IteratorAggregate implementation.
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Convert each item using the callback.
     */
    public function map(callable $callback): self
    {
        return new self(array_map($callback, $this->items));
    }

    /**
     * Convert collection to plain array.
     */
    public function toArray(): array
    {
        return array_map(function ($item) {
            if ($item instanceof Model) {
                return $item->toArray();
            }

            if ($item instanceof JsonSerializable) {
                return $item->jsonSerialize();
            }

            if (is_array($item)) {
                return $item;
            }

            if (is_object($item)) {
                return (array)$item;
            }

            return $item;
        }, $this->items);
    }

    /**
     * JsonSerializable implementation.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Get raw array (without converting nested models).
     */
    public function all(): array
    {
        return $this->items;
    }
}

