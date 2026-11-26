<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Enums;

/**
 * Value for the "Verify" field on create/update requests.
 */
enum Verify: string
{
    /** Save the record without starting verification. */
    case NotStarted = 'not-started';

    /** Submit for ACMA verification (record becomes read-only while pending). Entity must have an ABN. */
    case Complete = 'complete';

    /** Certified Participating Telcos only: verify on behalf of the entity. */
    case Overwrite = 'overwrite';
}
