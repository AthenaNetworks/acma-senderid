<?php

declare(strict_types=1);

/*
 * Read-only tests against the real ACMA API. They are skipped unless
 * credentials are provided via the environment:
 *
 *   ACMA_SENDERID_CLIENT_ID=... ACMA_SENDERID_CLIENT_SECRET=... composer test:live
 *
 * These tests never create, modify or delete records.
 */

use AthenaNetworks\AcmaSenderId\Data\NetworkProvider;
use AthenaNetworks\AcmaSenderId\Exceptions\AuthenticationException;
use AthenaNetworks\AcmaSenderId\Exceptions\NotFoundException;
use AthenaNetworks\AcmaSenderId\SenderIdClient;

beforeEach(function () {
    $id = getenv('ACMA_SENDERID_CLIENT_ID');
    $secret = getenv('ACMA_SENDERID_CLIENT_SECRET');

    if (! is_string($id) || $id === '' || ! is_string($secret) || $secret === '') {
        $this->markTestSkipped('Set ACMA_SENDERID_CLIENT_ID and ACMA_SENDERID_CLIENT_SECRET to run live tests.');
    }

    $this->client = SenderIdClient::create($id, $secret);
})->group('live');

it('authenticates and lists network providers', function () {
    $providers = $this->client->networkProviders()->list();

    expect($providers)->not->toBeEmpty()
        ->and($providers[0]->id)->toBe(NetworkProvider::DEFAULT_ID);
})->group('live');

it('lists brands, sender IDs and partners', function () {
    expect($this->client->brands()->list())->toBeArray()
        ->and($this->client->senderIds()->list(['not-started', 'pending', 'complete']))->toBeArray()
        ->and($this->client->partners()->list())->toBeArray();
})->group('live');

it('reads the notification subscription', function () {
    $subscription = $this->client->notifications()->get();

    expect($subscription === null || str_starts_with($subscription->callbackUri, 'https://'))->toBeTrue();
})->group('live');

it('checks for a valid use case', function () {
    expect($this->client->hasValidUseCase('51824753556', 'NOTREAL'))->toBeBool();
})->group('live');

it('raises NotFoundException for unknown IDs', function () {
    $this->client->chatbots()->get('00000000-0000-0000-0000-000000000001');
})->throws(NotFoundException::class)->group('live');

it('rejects bad credentials', function () {
    SenderIdClient::create('00000000-0000-0000-0000-000000000000', 'invalid-secret')->brands()->list();
})->throws(AuthenticationException::class)->group('live');
