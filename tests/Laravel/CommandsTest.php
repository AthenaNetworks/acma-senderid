<?php

declare(strict_types=1);

use AthenaNetworks\AcmaSenderId\Tests\Fixtures\MockHttpClient;
use GuzzleHttp\Psr7\Response;

it('checks connectivity', function () {
    $this->http
        ->queueJson(['NetworkProviders' => [['NetworkProviderId' => '00000000-0000-0000-0000-000000000000']]])
        ->queueJson(['Brands' => []])
        ->queueJson(['Chatbots' => []])
        ->queueJson(['Partners' => []])
        ->queueJson(['messages' => [['code' => '24400', 'message' => 'not found']]], 404);

    $this->artisan('senderid:check')
        ->expectsOutputToContain('Authentication')
        ->expectsOutputToContain('not subscribed')
        ->assertSuccessful();
});

it('reports authentication failures', function () {
    $this->http->queueToken(MockHttpClient::json(['error' => 'invalid_client', 'error_description' => 'bad secret'], 401));

    $this->artisan('senderid:check')->expectsOutputToContain('bad secret')->assertFailed();
});

it('lists sender ids', function () {
    $this->http->queueJson(['Chatbots' => [[
        'ChatbotId' => 'c1', 'ServiceName' => 'ATHENA', 'BrandName' => 'Athena', 'Verified' => 'pending',
        'x-ACMA-statusName' => 'Pending - Email Bounce', 'x-ACMA-expiresOn' => '2027-06-30T00:00:00Z',
    ]]]);

    $this->artisan('senderid:list', ['--status' => ['pending']])
        ->expectsTable(['ID', 'Sender ID', 'Brand', 'Verified', 'ACMA status', 'Expires'], [
            ['c1', 'ATHENA', 'Athena', 'pending', 'Pending - Email Bounce ⚠', '2027-06-30'],
        ])
        ->assertSuccessful();

    expect($this->http->lastRequest()->getUri()->getQuery())->toBe('verified=pending');
});

it('subscribes using the app routes and webhook credentials', function () {
    $this->http->queue(new Response(200));

    $this->artisan('senderid:notifications:subscribe', ['--force' => true, '--filter' => ['Chatbot']])->assertSuccessful();

    $body = $this->http->lastJson();
    parse_str((string) parse_url($body['AuthUri'], PHP_URL_QUERY), $query);

    expect($this->http->lastRequest()->getMethod())->toBe('PUT')
        ->and($body['CallbackUri'])->toBe('https://app.example/acma/senderid/notifications')
        ->and($body['AuthUri'])->toStartWith('https://app.example/acma/senderid/oauth/token?')
        ->and($query)->toBe(['grant_type' => 'client_credentials', 'client_id' => 'acma', 'client_secret' => 'a-very-long-webhook-secret'])
        ->and($body['Filter'])->toBe(['Chatbot']);
});

it('subscribes without authentication when auth is none', function () {
    config()->set('senderid.webhook.auth', 'none');
    $this->http->queue(new Response(200));

    $this->artisan('senderid:notifications:subscribe', ['--force' => true])->assertSuccessful();

    $body = $this->http->lastJson();
    expect($body['AuthUri'])->toBe($body['CallbackUri'])->and($body)->not->toHaveKey('Filter');
});

it('refuses to subscribe with an insecure callback', function () {
    $this->artisan('senderid:notifications:subscribe', ['--force' => true, '--callback' => 'http://insecure.example/cb', '--auth-uri' => 'https://x.example/t'])
        ->expectsOutputToContain('https://')
        ->assertFailed();

    expect($this->http->requests)->toBe([]);
});

it('requires webhook routes when no URLs are given', function () {
    config()->set('senderid.webhook.enabled', false);

    $this->artisan('senderid:notifications:subscribe', ['--force' => true])->assertFailed();
});

it('shows the subscription without leaking credentials', function () {
    $this->http->queueJson(['CallbackUri' => 'https://app.example/cb', 'AuthUri' => 'https://app.example/token?client_secret=topsecret', 'Filter' => []]);

    $this->artisan('senderid:notifications:show')
        ->expectsOutputToContain('https://app.example/cb')
        ->doesntExpectOutputToContain('topsecret')
        ->assertSuccessful();
});

it('unsubscribes', function () {
    $this->http->queue(new Response(200));

    $this->artisan('senderid:notifications:unsubscribe', ['--force' => true])->assertSuccessful();

    expect($this->http->lastRequest()->getMethod())->toBe('DELETE');
});
