<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

/**
 * Formatting and parsing for the x-ACMA-expiresOn field.
 *
 * Observed behaviour of the ACMA API (not documented in the OpenAPI spec):
 *
 *  - Requests must use .NET round-trip format with exactly 7 fractional
 *    digits, e.g. "2027-01-25T13:45:30.0000000Z". Other ISO 8601 forms
 *    (date only, no fraction, 3 or 6 fractional digits) are rejected with
 *    "Error in expiresOn. Invalid format.".
 *  - ACMA converts the value to Australia/Sydney local time and returns it
 *    without an offset on GET /chatbots/{id}, and with a misleading "Z" on
 *    GET /chatbots. Both are therefore interpreted as Sydney local time.
 *
 * @internal
 */
final class ExpiryDate
{
    public const string ACMA_TIMEZONE = 'Australia/Sydney';

    /**
     * Format for a request. Strings without an offset are treated as Sydney local time.
     */
    public static function format(DateTimeInterface|string $value): ?string
    {
        $date = $value instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($value)
            : self::parseInput($value);

        if ($date === null) {
            return null;
        }

        // PHP's "u" gives 6 fractional digits; ACMA requires exactly 7.
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u') . '0Z';
    }

    /**
     * Parse a user-supplied string. Explicit offsets are honoured.
     */
    public static function parseInput(string $value): ?DateTimeImmutable
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value, new DateTimeZone(self::ACMA_TIMEZONE));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Parse a value returned by ACMA, ignoring any offset (it is Sydney local time).
     */
    public static function parseResponse(?string $value): ?DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $local = preg_replace('/(Z|[+-]\d{2}:?\d{2})$/i', '', trim($value)) ?? $value;
        // PHP supports at most 6 fractional digits.
        $local = preg_replace('/(\.\d{6})\d+$/', '$1', $local) ?? $local;

        try {
            return new DateTimeImmutable($local, new DateTimeZone(self::ACMA_TIMEZONE));
        } catch (Throwable) {
            return null;
        }
    }
}
