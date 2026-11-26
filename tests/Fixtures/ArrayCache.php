<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Tests\Fixtures;

use DateInterval;
use Psr\SimpleCache\CacheInterface;

final class ArrayCache implements CacheInterface
{
    /** @var array<string, array{mixed, int|null}> */
    public array $items = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->has($key) ? $this->items[$key][0] : $default;
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $this->items[$key] = [$value, is_int($ttl) ? time() + $ttl : null];

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->items[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->items = [];

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        foreach ($keys as $key) {
            yield $key => $this->get($key, $default);
        }
    }

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return isset($this->items[$key]) && ($this->items[$key][1] === null || $this->items[$key][1] > time());
    }
}
