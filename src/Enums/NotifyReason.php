<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Enums;

/**
 * Why ACMA sent a push notification ("NotifyReason").
 */
enum NotifyReason: string
{
    case Create = 'Create';
    case Modify = 'Modify';
    case Delete = 'Delete';

    public static function fromLoose(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        foreach (self::cases() as $case) {
            if (strcasecmp($case->value, $value) === 0) {
                return $case;
            }
        }

        return null;
    }
}
