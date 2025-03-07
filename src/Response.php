<?php

namespace TONYLABS\Canvas;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use ArrayAccess;
use IteratorAggregate;
use ArrayIterator;

class Response implements ArrayAccess, IteratorAggregate
{
    protected array $data = [];

    public function __construct(array $data, string $tableName = '')
    {
        $this->data = $data;
    }

    public function __serialize(): array
    {
        return [
            'data' => $this->data
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->data = $data['data'];
    }

    // Implement ArrayAccess methods
    public function offsetExists($offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }

    // Implement IteratorAggregate
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->data);
    }

    public function count(): int
    {
        return count($this->data);
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function toJson(): string
    {
        return json_encode($this->data);
    }

    public function collect(): Collection
    {
        return collect($this->data);
    }
}
