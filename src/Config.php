<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId;

use AthenaNetworks\AcmaSenderId\Exceptions\InvalidArgumentException;

/**
 * Connection settings for the ACMA Sender ID API.
 */
final readonly class Config
{
    public const string DEFAULT_BASE_URI = 'https://backend.acma.gov.au/sndr/';

    public const string DEFAULT_TOKEN_URL = 'https://acmagovaub2c.b2clogin.com/acmagovaub2c.onmicrosoft.com/b2c_1a_acma_signup_signin/oauth2/v2.0/token';

    public const string DEFAULT_SCOPE = 'https://acmagovaub2c.onmicrosoft.com/AcmaApis/.default';

    public const string DEFAULT_VERSION = 'v1';

    public string $baseUri;

    public string $version;

    public function __construct(
        public string $clientId,
        #[\SensitiveParameter]
        public string $clientSecret,
        string $baseUri = self::DEFAULT_BASE_URI,
        public string $tokenUrl = self::DEFAULT_TOKEN_URL,
        public string $scope = self::DEFAULT_SCOPE,
        string $version = self::DEFAULT_VERSION,
        public string $userAgent = 'athenanetworks/acma-senderid',
    ) {
        if (trim($clientId) === '') {
            throw InvalidArgumentException::forField('clientId', 'must not be empty');
        }
        if (trim($clientSecret) === '') {
            throw InvalidArgumentException::forField('clientSecret', 'must not be empty');
        }

        $this->baseUri = rtrim($baseUri, '/') . '/';
        $version = strtolower(trim($version));
        $this->version = str_starts_with($version, 'v') ? $version : 'v' . $version;
    }

    /**
     * @param array{client_id?: ?string, client_secret?: ?string, base_uri?: ?string, token_url?: ?string, scope?: ?string, version?: ?string} $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            clientId: (string) ($config['client_id'] ?? ''),
            clientSecret: (string) ($config['client_secret'] ?? ''),
            baseUri: ($config['base_uri'] ?? null) ?: self::DEFAULT_BASE_URI,
            tokenUrl: ($config['token_url'] ?? null) ?: self::DEFAULT_TOKEN_URL,
            scope: ($config['scope'] ?? null) ?: self::DEFAULT_SCOPE,
            version: ($config['version'] ?? null) ?: self::DEFAULT_VERSION,
        );
    }

    public function __debugInfo(): array
    {
        return [
            'clientId' => $this->clientId,
            'clientSecret' => '***',
            'baseUri' => $this->baseUri,
            'tokenUrl' => $this->tokenUrl,
            'scope' => $this->scope,
            'version' => $this->version,
        ];
    }
}
