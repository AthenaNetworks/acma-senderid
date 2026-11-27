<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Resources;

use AthenaNetworks\AcmaSenderId\Exceptions\UnexpectedResponseException;
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

    /**
     * The ID of a record ACMA just created. A success without one is not a
     * usable answer: the record probably exists, but its ID is unknown.
     *
     * @param array<array-key, mixed> $response
     *
     * @throws UnexpectedResponseException
     */
    public static function created(array $response, string $key): string
    {
        $id = Attributes::of($response)->string($key);
        if ($id === null || trim($id) === '') {
            throw new UnexpectedResponseException(
                "ACMA Sender ID API accepted the create but returned no {$key}; the record may have been created",
                0,
            );
        }

        return $id;
    }
}
