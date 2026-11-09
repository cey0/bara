<?php

declare(strict_types=1);

namespace Framework\Database;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Minimal collection returned by every query, like Eloquent's Collection.
 *
 * @implements ArrayAccess<int|string, mixed>
 * @implements IteratorAggregate<int|string, mixed>
 */
final class Collection implements ArrayAccess, IteratorAggregate, Countable
{
    public function __construct(private array $items = [])
    {
    }

    public function all(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function isNotEmpty(): bool
    {
        return $this->items !== [];
    }

    public function first(?callable $callback = null)
    {
        foreach ($this->items as $item) {
            if ($callback === null || $callback($item)) {
                return $item;
            }
        }

        return null;
    }

    public function map(callable $callback): self
    {
        return new self(array_map($callback, $this->items));
    }

    public function filter(?callable $callback = null): self
    {
        return new self(array_values(array_filter($this->items, $callback)));
    }

    public function each(callable $callback): self
    {
        foreach ($this->items as $key => $item) {
            $callback($item, $key);
        }

        return $this;
    }

    public function pluck(string $key): self
    {
        $values = [];

        foreach ($this->items as $item) {
            if ($item instanceof Model) {
                $values[] = $item->getAttribute($key);
            } elseif (is_array($item) && array_key_exists($key, $item)) {
                $values[] = $item[$key];
            }
        }

        return new self($values);
    }

    public function toArray(): array
    {
        return array_map(static function ($item) {
            return $item instanceof Model ? $item->toArray() : $item;
        }, $this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;

            return;
        }

        $this->items[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }
}
