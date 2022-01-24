<?php

namespace TONYLABS\Canvas;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class Response implements \Iterator, \ArrayAccess
{
    public ?array $data;

    public function __construct(array $data, string $key)
    {
        $this->data = $this->inferData($data, strtolower($key));
    }

    protected function inferData(array $data, string $key): array
    {
        if (empty($data)) {
            return [];
        }
        if (isset($data[$key])) {
            return $data[$key];
        }
        if ($nested = Arr::get($data, $key . 's')) {
            return $this->inferData($nested, $key);
        }
        if (count(array_keys($data)) === 1) {
            return $this->inferData(Arr::first($data), '');
        }
        return $data;
    }

    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function isEmpty(): bool
    {
        return empty($this->data);
    }

    public function count(): int
    {
        return count($this->data);
    }

    public function current(): mixed
    {
        $current = $this->data[$this->index] ?? null;
        if (!$current || !Arr::has($current, 'tables')) return $current;
        return Arr::get($current, "tables.{$this->tableName}");
    }

    public function next(): void
    {
        $this->index++;
    }

    public function key(): int
    {
        return $this->index;
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
        $this->data = $data['data'] ?? [];
        $this->tableName = $data['table_name'] ?? null;
        $this->expansions = $data['expansions'] ?? [];
        $this->extensions = $data['extensions'] ?? [];
    }
}
