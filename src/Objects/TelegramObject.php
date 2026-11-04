<?php

declare(strict_types=1);

namespace Amirxd\UltraTelegram\Objects;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use LogicException;
use Traversable;

class TelegramObject implements ArrayAccess, IteratorAggregate, JsonSerializable
{
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!array_key_exists($key, $this->data)) {
            return $default;
        }

        return $this->wrapValue($this->data[$key]);
    }

    public function getRaw(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->data)
            ? $this->data[$key]
            : $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function keys(): array
    {
        return array_keys($this->data);
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function toJson(
        int $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ): string {
        return json_encode($this->data, $flags | JSON_THROW_ON_ERROR);
    }

    public function jsonSerialize(): array
    {
        return $this->data;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->data);
    }

    public function offsetExists(mixed $offset): bool
    {
        return is_string($offset) || is_int($offset)
            ? array_key_exists($offset, $this->data)
            : false;
    }

    public function offsetGet(mixed $offset): mixed
    {
        return is_string($offset) || is_int($offset)
            ? $this->get((string) $offset)
            : null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('Telegram response objects are read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('Telegram response objects are read-only.');
    }

    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    public function __isset(string $name): bool
    {
        return $this->has($name);
    }

    private function wrapValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (self::isList($value)) {
            $result = [];

            foreach ($value as $item) {
                $result[] = $this->wrapValue($item);
            }

            return $result;
        }

        return new self($value);
    }

    private static function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}
