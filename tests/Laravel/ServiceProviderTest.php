<?php

declare(strict_types=1);

use AthenaNetworks\AcmaSenderId\Laravel\Facades\SenderId;
use AthenaNetworks\AcmaSenderId\SenderIdClient;
use AthenaNetworks\AcmaSenderId\Tests\Fixtures\MockHttpClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

it('binds a configured client singleton', function () {
    $client = app(SenderIdClient::class);

    expect($client)->toBe(app('senderid'))
        ->and($client->config->clientId)->toBe('laravel-client')
        ->and($client->config->baseUri)->toBe('https://backend.acma.gov.au/sndr/');
});

it('uses the bound PSR-18 client and Laravel cache for tokens', function () {
    $this->http->queueJson(['Brands' => []])->queueJson(['Chatbots' => []]);

    SenderId::brands()->list();
    $this->app->forgetInstance(SenderIdClient::class);
    SenderId::clearResolvedInstances();
    SenderId::senderIds()->list();

    expect($this->http->tokensIssued)->toBe(1)
        ->and($this->http->requests[1]->getHeaderLine('Authorization'))->toBe('Bearer token-1');
});

it('can disable the shared token cache', function () {
    config()->set('senderid.cache.store', false);
    $this->http->queueJson(['Brands' => []])->queueJson(['Brands' => []]);

    app(SenderIdClient::class)->brands()->list();
    $this->app->forgetInstance(SenderIdClient::class);
    app(SenderIdClient::class)->brands()->list();

    expect($this->http->tokensIssued)->toBe(2);
});

it('registers webhook routes', function () {
    expect(Route::has('senderid.webhook.token'))->toBeTrue()
        ->and(Route::has('senderid.webhook.notifications'))->toBeTrue()
        ->and(route('senderid.webhook.notifications'))->toBe('https://app.example/acma/senderid/notifications');
});
