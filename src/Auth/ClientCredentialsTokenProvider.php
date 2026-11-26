<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Auth;

use AthenaNetworks\AcmaSenderId\Config;
use AthenaNetworks\AcmaSenderId\Exceptions\AuthenticationException;
use AthenaNetworks\AcmaSenderId\Exceptions\TransportException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException as CacheInvalidArgumentException;

/**
 * Obtains tokens from ACMA's Azure AD B2C endpoint using the OAuth 2.0
 * Client Credentials grant.
 *
 * Tokens are always kept in memory for the lifetime of this object. When a
 * PSR-16 cache is supplied they are also shared across processes/requests.
 */
final class ClientCredentialsTokenProvider implements TokenProvider
{
    /** Refresh tokens this many seconds before they actually expire. */
    public const int EXPIRY_LEEWAY = 60;

    private ?AccessToken $token = null;

    public function __construct(
        private readonly Config $config,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly ?CacheInterface $cache = null,
        private readonly string $cachePrefix = 'acma_senderid_token_',
    ) {
    }

    public function getToken(): AccessToken
    {
        if ($this->token !== null && ! $this->token->isExpired(self::EXPIRY_LEEWAY)) {
            return $this->token;
        }

        $cached = $this->readCache();
        if ($cached !== null && ! $cached->isExpired(self::EXPIRY_LEEWAY)) {
            return $this->token = $cached;
        }

        $this->token = $this->requestToken();
        $this->writeCache($this->token);

        return $this->token;
    }

    public function invalidate(): void
    {
        $this->token = null;

        try {
            $this->cache?->delete($this->cacheKey());
        } catch (CacheInvalidArgumentException) {
            // Ignore: the key is generated internally and always valid.
        }
    }

    private function requestToken(): AccessToken
    {
        $body = http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $this->config->clientId,
            'client_secret' => $this->config->clientSecret,
            'scope' => $this->config->scope,
        ], '', '&', PHP_QUERY_RFC3986);

        $request = $this->requestFactory->createRequest('POST', $this->config->tokenUrl)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->config->userAgent)
            ->withBody($this->streamFactory->createStream($body));

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new TransportException('Unable to reach the ACMA token endpoint: ' . $e->getMessage(), 0, $e);
        }

        $status = $response->getStatusCode();
        $decoded = json_decode((string) $response->getBody(), true);
        $data = is_array($decoded) ? $decoded : [];

        if ($status < 200 || $status >= 300 || ! isset($data['access_token']) || ! is_string($data['access_token'])) {
            $reason = match (true) {
                isset($data['error_description']) && is_string($data['error_description']) => trim(strtok($data['error_description'], "\r\n") ?: $data['error_description']),
                isset($data['error']) && is_string($data['error']) => $data['error'],
                default => "HTTP {$status}",
            };

            throw AuthenticationException::tokenRequestFailed($reason, $status);
        }

        $expiresAt = match (true) {
            isset($data['expires_on']) && is_numeric($data['expires_on']) => (int) $data['expires_on'],
            isset($data['expires_in']) && is_numeric($data['expires_in']) => time() + (int) $data['expires_in'],
            default => time() + 3600,
        };

        return new AccessToken(
            $data['access_token'],
            $expiresAt,
            isset($data['token_type']) && is_string($data['token_type']) ? $data['token_type'] : 'Bearer',
        );
    }

    private function cacheKey(): string
    {
        // PSR-16 keys may not contain {}()/\@: so hash the identifying values.
        return $this->cachePrefix . hash('sha256', $this->config->tokenUrl . '|' . $this->config->clientId . '|' . $this->config->scope);
    }

    private function readCache(): ?AccessToken
    {
        if ($this->cache === null) {
            return null;
        }

        try {
            $value = $this->cache->get($this->cacheKey());
        } catch (CacheInvalidArgumentException) {
            return null;
        }

        if (! is_array($value) || ! isset($value['token'], $value['expires_at']) || ! is_string($value['token']) || ! is_int($value['expires_at'])) {
            return null;
        }

        return new AccessToken($value['token'], $value['expires_at'], is_string($value['type'] ?? null) ? $value['type'] : 'Bearer');
    }

    private function writeCache(AccessToken $token): void
    {
        $ttl = $token->secondsRemaining() - self::EXPIRY_LEEWAY;
        if ($this->cache === null || $ttl <= 0) {
            return;
        }

        try {
            $this->cache->set($this->cacheKey(), [
                'token' => $token->token,
                'expires_at' => $token->expiresAt,
                'type' => $token->type,
            ], $ttl);
        } catch (CacheInvalidArgumentException) {
            // Ignore: caching is best-effort.
        }
    }
}
