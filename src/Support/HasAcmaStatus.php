<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Support;

/**
 * Helpers for ACMA's x-ACMA-statusName extension field.
 *
 * @internal
 */
trait HasAcmaStatus
{
    /**
     * Whether an email sent by the Sender ID Register to the contact bounced.
     * When true, review and update the contact email address.
     */
    public function hasEmailBounce(): bool
    {
        return $this->acmaStatusName !== null && stripos($this->acmaStatusName, 'Email Bounce') !== false;
    }
}
