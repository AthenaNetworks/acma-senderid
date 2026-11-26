<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Exceptions;

/**
 * Thrown when an access token cannot be obtained, or the API rejects the token (401).
 */
class AuthenticationException extends ApiException
{
    public static function tokenRequestFailed(string $reason, int $statusCode = 0): self
    {
        return new self('Unable to obtain an ACMA access token: ' . $reason, $statusCode);
    }
}
