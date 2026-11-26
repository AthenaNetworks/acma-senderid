<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Resources;

use AthenaNetworks\AcmaSenderId\Support\Attributes;

/**
 * @internal
 */
final class Ids
{
    /**
     * @param array<array-key, mixed> $response
     */
    public static function extract(array $response, string $key, string $fallback = ''): string
    {
        return Attributes::of($response)->string($key) ?? $fallback;
    }
}
