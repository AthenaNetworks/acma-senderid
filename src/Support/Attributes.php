<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Support;

use DateTimeImmutable;
use Throwable;

/**
 * Typed, case-insensitive accessors for decoded API payloads.
 *
 * The API documents PascalCase keys but some examples (and push notifications)
 * use camelCase, so lookups ignore case.
 *
 * @internal
 */
final class Attributes
{
    /**
     * @param array<array-key, mixed> $data
     */
    public function __construct(private readonly array $data)
    {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function of(array $data): self
    {
        return new self($data);
    }

    public function has(string $key): bool
    {
        return $this->find($key) !== null;
    }

    public function raw(string $key): mixed
    {
        $found = $this->find($key);

        return $found === null ? null : $this->data[$found];
    }

    public function string(string $key): ?string
    {
        $value = $this->raw($key);

        return is_scalar($value) ? (string) $value : null;
    }

    public function int(string $key): ?int
    {
        $value = $this->raw($key);

        return is_numeric($value) ? (int) $value : null;
    }

    public function bool(string $key): ?bool
    {
        $value = $this->raw($key);

        return match (true) {
            is_bool($value) => $value,
            $value === 'true', $value === 1, $value === '1' => true,
            $value === 'false', $value === 0, $value === '0' => false,
            default => null,
        };
    }

    public function date(string $key): ?DateTimeImmutable
    {
        $value = $this->string($key);
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    public function array(string $key): array
    {
        $value = $this->raw($key);

        return is_array($value) ? $value : [];
    }

    /**
     * @return list<string>
     */
    public function strings(string $key): array
    {
        return array_values(array_map(strval(...), array_filter($this->array($key), is_scalar(...))));
    }

    private function find(string $key): string|int|null
    {
        if (array_key_exists($key, $this->data)) {
            return $key;
        }

        foreach (array_keys($this->data) as $candidate) {
            if (strcasecmp((string) $candidate, $key) === 0) {
                return $candidate;
            }
        }

        return null;
    }
}
