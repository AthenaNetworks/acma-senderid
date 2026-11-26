<?php

declare(strict_types=1);

use AthenaNetworks\AcmaSenderId\Auth\ClientCredentialsTokenProvider;
use AthenaNetworks\AcmaSenderId\Config;
use AthenaNetworks\AcmaSenderId\Exceptions\AuthenticationException;
use AthenaNetworks\AcmaSenderId\Exceptions\TransportException;
use AthenaNetworks\AcmaSenderId\Tests\Fixtures\ArrayCache;
use AthenaNetworks\AcmaSenderId\Tests\Fixtures\MockHttpClient;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\ConnectException;

function tokenProvider(MockHttpClient $http, ?ArrayCache $cache = null): ClientCredentialsTokenProvider
{
    $factory = new HttpFactory();

    return new ClientCredentialsTokenProvider(new Config('my-id', 'my secret&'), $http, $factory, $factory, $cache);
}

it('requests a token with the client credentials grant', function () {
    $http = new MockHttpClient();
    $token = tokenProvider($http)->getToken();

    expect($token->token)->toBe('token-1');

    $request = $http->tokenRequests[0];
    parse_str((string) $request->getBody(), $form);

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe(Config::DEFAULT_TOKEN_URL)
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/x-www-form-urlencoded')
        ->and($form)->toBe([
            'grant_type' => 'client_credentials',
            'client_id' => 'my-id',
            'client_secret' => 'my secret&',
            'scope' => Config::DEFAULT_SCOPE,
        ]);
});

it('reuses the token in memory until it nears expiry', function () {
    $http = new MockHttpClient();
    $provider = tokenProvider($http);

    $provider->getToken();
    $provider->getToken();
    expect($http->tokensIssued)->toBe(1);

    $http->queueToken(MockHttpClient::json(['access_token' => 'short', 'expires_in' => 30]));
    $provider->invalidate();
    expect($provider->getToken()->token)->toBe('short');
    // 30s left is inside the 60s leeway, so the next call refreshes.
    expect($provider->getToken()->token)->toBe('token-2');
});

it('prefers expires_on when provided', function () {
    $http = (new MockHttpClient())->queueToken(MockHttpClient::json(['access_token' => 'x', 'expires_in' => 3600, 'expires_on' => '1999999999']));

    expect(tokenProvider($http)->getToken()->expiresAt)->toBe(1999999999);
});

it('shares tokens through a PSR-16 cache', function () {
    $http = new MockHttpClient();
    $cache = new ArrayCache();

    tokenProvider($http, $cache)->getToken();
    $second = tokenProvider($http, $cache)->getToken();

    expect($http->tokensIssued)->toBe(1)
        ->and($second->token)->toBe('token-1')
        ->and(array_key_first($cache->items))->toStartWith('acma_senderid_token_')
        ->and(array_key_first($cache->items))->not->toContain('my-id');
});

it('removes the cached token on invalidate', function () {
    $cache = new ArrayCache();
    $provider = tokenProvider(new MockHttpClient(), $cache);
    $provider->getToken();

    $provider->invalidate();

    expect($cache->items)->toBe([]);
});

it('throws an AuthenticationException with the error description', function () {
    $http = (new MockHttpClient())->queueToken(MockHttpClient::json([
        'error' => 'invalid_client',
        'error_description' => "AADB2C90081: The specified client_secret does not match.\r\nCorrelation ID: abc",
    ], 401));

    expect(fn () => tokenProvider($http)->getToken())
        ->toThrow(AuthenticationException::class, 'AADB2C90081: The specified client_secret does not match.');
});

it('throws an AuthenticationException when no token is returned', function () {
    $http = (new MockHttpClient())->queueToken(MockHttpClient::json([]));

    expect(fn () => tokenProvider($http)->getToken())->toThrow(AuthenticationException::class, 'HTTP 200');
});

it('wraps network errors', function () {
    $http = (new MockHttpClient())->queueToken(new ConnectException('Could not resolve host', new Request('POST', 'https://x')));

    expect(fn () => tokenProvider($http)->getToken())->toThrow(TransportException::class, 'Could not resolve host');
});
