<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Webhook;

use AthenaNetworks\AcmaSenderId\Auth\AccessToken;
use AthenaNetworks\AcmaSenderId\Exceptions\InvalidArgumentException;

/**
 * A minimal OAuth 2.0 client-credentials token issuer for ACMA push notifications.
 *
 * Point the subscription's AuthUri at an endpoint backed by issue(), with your
 * webhook client_id and client_secret in its query string. ACMA requests a
 * token there and sends it as "Authorization: Bearer ..." with each
 * notification, which you check with verifyAuthorizationHeader().
 *
 * Tokens are stateless HS256 JWTs signed with a key derived from the secret,
 * so rotating the secret invalidates outstanding tokens.
 */
final class NotificationTokenIssuer
{
    private const string AUDIENCE = 'acma-senderid-webhook';

    private readonly string $signingKey;

    public function __construct(
        private readonly string $clientId,
        #[\SensitiveParameter]
        private readonly string $clientSecret,
        private readonly int $ttl = 3600,
    ) {
        if ($clientId === '' || $clientSecret === '') {
            throw new InvalidArgumentException('Webhook client_id and client_secret must be configured to issue notification tokens.');
        }
        if (strlen($clientSecret) < 16) {
            throw InvalidArgumentException::forField('webhook.client_secret', 'must be at least 16 characters');
        }

        $this->signingKey = hash_hmac('sha256', $clientId, $clientSecret, true);
    }

    /**
     * Exchange client credentials for a bearer token.
     *
     * @throws InvalidClientException
     */
    public function issue(?string $clientId, #[\SensitiveParameter] ?string $clientSecret, ?int $now = null): AccessToken
    {
        if (! $this->credentialsMatch($clientId, $clientSecret)) {
            throw new InvalidClientException('Invalid client credentials.');
        }

        $now ??= time();
        $expiresAt = $now + $this->ttl;

        $header = self::base64UrlEncode((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = self::base64UrlEncode((string) json_encode([
            'iss' => self::AUDIENCE,
            'aud' => self::AUDIENCE,
            'sub' => $this->clientId,
            'iat' => $now,
            'exp' => $expiresAt,
            'jti' => bin2hex(random_bytes(8)),
        ]));
        $signature = self::base64UrlEncode(hash_hmac('sha256', "{$header}.{$payload}", $this->signingKey, true));

        return new AccessToken("{$header}.{$payload}.{$signature}", $expiresAt);
    }

    /**
     * Whether a bearer token was issued by this issuer and has not expired.
     */
    public function verify(#[\SensitiveParameter] string $token, ?int $now = null): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        [$header, $payload, $signature] = $parts;
        $expected = self::base64UrlEncode(hash_hmac('sha256', "{$header}.{$payload}", $this->signingKey, true));
        if (! hash_equals($expected, $signature)) {
            return false;
        }

        $headerData = json_decode(self::base64UrlDecode($header), true);
        $claims = json_decode(self::base64UrlDecode($payload), true);
        if (! is_array($headerData) || ($headerData['alg'] ?? null) !== 'HS256' || ! is_array($claims)) {
            return false;
        }

        return ($claims['aud'] ?? null) === self::AUDIENCE
            && ($claims['sub'] ?? null) === $this->clientId
            && is_int($claims['exp'] ?? null)
            && $claims['exp'] > ($now ?? time());
    }

    /**
     * Verify an "Authorization: Bearer <token>" header value.
     */
    public function verifyAuthorizationHeader(#[\SensitiveParameter] ?string $header, ?int $now = null): bool
    {
        if ($header === null || preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $header, $m) !== 1) {
            return false;
        }

        return $this->verify($m[1], $now);
    }

    public function ttl(): int
    {
        return $this->ttl;
    }

    /**
     * Build the AuthUri to register with ACMA for a given token endpoint URL.
     */
    public function authUri(string $tokenEndpoint): string
    {
        $query = http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ], '', '&', PHP_QUERY_RFC3986);

        return $tokenEndpoint . (str_contains($tokenEndpoint, '?') ? '&' : '?') . $query;
    }

    private function credentialsMatch(?string $clientId, ?string $clientSecret): bool
    {
        // Evaluate both comparisons to avoid leaking which one failed via timing.
        $idOk = hash_equals($this->clientId, (string) $clientId);
        $secretOk = hash_equals($this->clientSecret, (string) $clientSecret);

        return $idOk && $secretOk;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'), true);
    }

    public function __debugInfo(): array
    {
        return ['clientId' => $this->clientId, 'clientSecret' => '***', 'ttl' => $this->ttl];
    }
}
