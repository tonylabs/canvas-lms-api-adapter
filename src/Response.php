<?php

namespace TONYLABS\Canvas;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class Response implements \ArrayAccess, \Iterator, \Countable
{
    protected array $data = [];
    protected string $tableName;
    protected array $expansions = [];
    protected array $extensions = [];
    protected int $index = 0;

    public function __construct(array $data, string $tableName = '')
    {
        $this->data = $data;
        $this->tableName = $tableName;
    }

    public function count(): int
    {
        return count($this->data);
    }

    public function current(): mixed
    {
        return $this->data[$this->index];
    }

    public function key(): int
    {
        return $this->index;
    }

    public function next(): void
    {
        $this->index++;
    }

    public function valid(): bool
    {
        return isset($this->data[$this->index]);
    }

    public function rewind(): void
    {
        $this->index = 0;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return Arr::get($this->data, $offset);
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

    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
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

    public function __serialize(): array
    {
        return [
            'data' => $this->data,
            'table_name' => $this->tableName,
            'expansions' => $this->expansions,
            'extensions' => $this->extensions,
        ];
    }
    
    public function __unserialize(array $data): void
    {
        $this->data = $data['data'];
        $this->tableName = $data['table_name'];
        $this->expansions = $data['expansions'];
        $this->extensions = $data['extensions'];
    }
}
