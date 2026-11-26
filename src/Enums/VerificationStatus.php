<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Enums;

/**
 * Verification lifecycle status reported by the API (and accepted as a list filter).
 */
enum VerificationStatus: string
{
    case NotStarted = 'not-started';
    case NotComplete = 'not-complete';
    case Pending = 'pending';
    case Complete = 'complete';
    case Failed = 'failed';
    case Expired = 'expired';

    public function isFinal(): bool
    {
        return match ($this) {
            self::Complete, self::Failed, self::Expired => true,
            default => false,
        };
    }
}
