<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Auth;

/**
 * An OAuth 2.0 bearer token and its expiry (unix timestamp).
 */
final readonly class AccessToken
{
    public function __construct(
        #[\SensitiveParameter]
        public string $token,
        public int $expiresAt,
        public string $type = 'Bearer',
    ) {
    }

    /**
     * Whether the token has expired, or will within $leeway seconds.
     */
    public function isExpired(int $leeway = 0, ?int $now = null): bool
    {
        return ($now ?? time()) + $leeway >= $this->expiresAt;
    }

    public function secondsRemaining(?int $now = null): int
    {
        return max(0, $this->expiresAt - ($now ?? time()));
    }

    public function __debugInfo(): array
    {
        return ['token' => '***', 'expiresAt' => $this->expiresAt, 'type' => $this->type];
    }
}
