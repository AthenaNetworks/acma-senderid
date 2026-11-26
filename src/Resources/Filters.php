<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Resources;

use AthenaNetworks\AcmaSenderId\Enums\VerificationStatus;

/**
 * @internal
 */
final class Filters
{
    /**
     * @param list<VerificationStatus|string>|VerificationStatus|string|null $statuses
     * @return list<string>|null
     */
    public static function statuses(array|VerificationStatus|string|null $statuses): ?array
    {
        if ($statuses === null || $statuses === []) {
            return null;
        }

        $statuses = is_array($statuses) ? $statuses : [$statuses];

        return array_values(array_map(static fn (VerificationStatus|string $s): string => $s instanceof VerificationStatus ? $s->value : $s, $statuses));
    }
}
