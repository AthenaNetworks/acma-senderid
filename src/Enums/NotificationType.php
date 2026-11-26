<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Enums;

/**
 * Push notification change types supported by ACMA.
 */
enum NotificationType: string
{
    case Partner = 'Partner';
    case Brand = 'Brand';
    case Chatbot = 'Chatbot';

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
