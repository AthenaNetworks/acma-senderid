<?php

use AthenaNetworks\AcmaSenderId\Webhook\AcmaIpAddresses;

return [

    /*
    |--------------------------------------------------------------------------
    | API credentials
    |--------------------------------------------------------------------------
    |
    | Generate a Client ID and Secret with "Get authentication details" in the
    | ACMA portal. Your identity needs the "Sender ID Originating Telco" role.
    | ACMA does not expire secrets: you are responsible for storing and
    | rotating them securely.
    |
    */

    'client_id' => env('ACMA_SENDERID_CLIENT_ID'),

    'client_secret' => env('ACMA_SENDERID_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Endpoints
    |--------------------------------------------------------------------------
    |
    | Defaults point at ACMA production. Only change these if ACMA tells you to.
    |
    */

    'base_uri' => env('ACMA_SENDERID_BASE_URI', 'https://backend.acma.gov.au/sndr/'),

    'token_url' => env('ACMA_SENDERID_TOKEN_URL', 'https://acmagovaub2c.b2clogin.com/acmagovaub2c.onmicrosoft.com/b2c_1a_acma_signup_signin/oauth2/v2.0/token'),

    'scope' => env('ACMA_SENDERID_SCOPE', 'https://acmagovaub2c.onmicrosoft.com/AcmaApis/.default'),

    'version' => env('ACMA_SENDERID_API_VERSION', 'v1'),

    /*
    |--------------------------------------------------------------------------
    | Access token cache
    |--------------------------------------------------------------------------
    |
    | Access tokens last about an hour. Caching them in a shared store avoids
    | fetching a new token on every request/job. Set "store" to null to use
    | the default cache store, or false to keep tokens in memory only.
    |
    */

    'cache' => [
        'store' => env('ACMA_SENDERID_CACHE_STORE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Push notification webhook
    |--------------------------------------------------------------------------
    |
    | When enabled, two routes are registered:
    |
    |   POST {prefix}/oauth/token     -> issues bearer tokens to ACMA (AuthUri)
    |   POST {prefix}/notifications   -> receives notifications (CallbackUri)
    |
    | Each notification dispatches NotificationReceived, then BrandChanged,
    | ChatbotChanged or PartnerChanged when the type is recognised.
    |
    | Run `php artisan senderid:notifications:subscribe` to register the URLs
    | with ACMA. Both must be publicly reachable over HTTPS.
    |
    | "auth" may be "token" (recommended) or "none". With "none" the AuthUri is
    | set to the CallbackUri, which tells ACMA to skip authentication.
    |
    */

    'webhook' => [
        'enabled' => env('ACMA_SENDERID_WEBHOOK_ENABLED', false),

        'prefix' => env('ACMA_SENDERID_WEBHOOK_PREFIX', 'acma/senderid'),

        'domain' => env('ACMA_SENDERID_WEBHOOK_DOMAIN'),

        'middleware' => [],

        'auth' => env('ACMA_SENDERID_WEBHOOK_AUTH', 'token'),

        // Credentials ACMA presents to your token endpoint. Choose your own
        // values; the secret must be at least 16 characters.
        'client_id' => env('ACMA_SENDERID_WEBHOOK_CLIENT_ID'),

        'client_secret' => env('ACMA_SENDERID_WEBHOOK_CLIENT_SECRET'),

        'token_ttl' => (int) env('ACMA_SENDERID_WEBHOOK_TOKEN_TTL', 3600),

        // Restrict both routes to these source IPs. Empty disables the check.
        // Make sure trusted proxies are configured so the client IP is correct.
        // e.g. AcmaIpAddresses::ALL
        'allowed_ips' => [],

        // Notification types to subscribe to: Partner, Brand, Chatbot. Empty = all.
        'filter' => [],
    ],

];
