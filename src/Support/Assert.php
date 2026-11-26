<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Support;

use AthenaNetworks\AcmaSenderId\Exceptions\InvalidArgumentException;

/**
 * Client-side validation helpers mirroring constraints in the OpenAPI spec.
 *
 * @internal
 */
final class Assert
{
    public static function required(string $field, mixed $value): void
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            throw InvalidArgumentException::forField($field, 'is required');
        }
    }

    public static function length(string $field, ?string $value, int $min = 0, ?int $max = null): void
    {
        if ($value === null) {
            return;
        }

        $length = mb_strlen($value);
        if ($length < $min) {
            throw InvalidArgumentException::forField($field, "must be at least {$min} characters");
        }
        if ($max !== null && $length > $max) {
            throw InvalidArgumentException::forField($field, "must not exceed {$max} characters (got {$length})");
        }
    }

    public static function httpsUrl(string $field, ?string $value): void
    {
        if ($value === null) {
            return;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);
        $host = parse_url($value, PHP_URL_HOST);
        if (! is_string($scheme) || strtolower($scheme) !== 'https' || ! is_string($host) || $host === '') {
            throw InvalidArgumentException::forField($field, "must be an absolute https:// URL (got \"{$value}\")");
        }
    }

    public static function countryCode(string $field, ?string $value): void
    {
        if ($value !== null && preg_match('/^[A-Za-z]{2}$/', $value) !== 1) {
            throw InvalidArgumentException::forField($field, "must be an ISO 3166-1 alpha-2 country code, e.g. \"AU\" (got \"{$value}\")");
        }
    }

    /**
     * @param array<array-key, mixed>|null $value
     */
    public static function maxItems(string $field, ?array $value, int $max): void
    {
        if ($value !== null && count($value) > $max) {
            throw InvalidArgumentException::forField($field, "must not contain more than {$max} items");
        }
    }

    /**
     * @param list<string> $allowed
     */
    public static function oneOf(string $field, ?string $value, array $allowed): void
    {
        if ($value !== null && ! in_array($value, $allowed, true)) {
            throw InvalidArgumentException::forField($field, sprintf('must be one of "%s" (got "%s")', implode('", "', $allowed), $value));
        }
    }
}
