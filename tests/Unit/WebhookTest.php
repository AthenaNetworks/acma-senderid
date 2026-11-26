<?php

declare(strict_types=1);

use AthenaNetworks\AcmaSenderId\Enums\NotificationType;
use AthenaNetworks\AcmaSenderId\Enums\NotifyReason;
use AthenaNetworks\AcmaSenderId\Exceptions\InvalidArgumentException;
use AthenaNetworks\AcmaSenderId\Webhook\InvalidClientException;
use AthenaNetworks\AcmaSenderId\Webhook\Notification;
use AthenaNetworks\AcmaSenderId\Webhook\NotificationTokenIssuer;

const WEBHOOK_SECRET = 'a-very-long-webhook-secret';

describe('token issuer', function () {
    beforeEach(fn () => $this->issuer = new NotificationTokenIssuer('acma', WEBHOOK_SECRET, 600));

    it('issues verifiable tokens', function () {
        $token = $this->issuer->issue('acma', WEBHOOK_SECRET);

        expect($token->expiresAt)->toBeGreaterThan(time() + 590)
            ->and($this->issuer->verify($token->token))->toBeTrue()
            ->and($this->issuer->verifyAuthorizationHeader('Bearer ' . $token->token))->toBeTrue()
            ->and($this->issuer->verifyAuthorizationHeader('bearer  ' . $token->token . ' '))->toBeTrue();
    });

    it('rejects wrong credentials', function (?string $id, ?string $secret) {
        $this->issuer->issue($id, $secret);
    })->with([
        ['acma', 'wrong-secret-xxxxxxxx'],
        ['other', WEBHOOK_SECRET],
        [null, null],
    ])->throws(InvalidClientException::class);

    it('rejects expired tokens', function () {
        $token = $this->issuer->issue('acma', WEBHOOK_SECRET, now: time() - 601);

        expect($this->issuer->verify($token->token))->toBeFalse();
    });

    it('rejects tampered or foreign tokens', function () {
        $token = $this->issuer->issue('acma', WEBHOOK_SECRET)->token;
        [$h, $p, $s] = explode('.', $token);
        $forgedPayload = rtrim(strtr(base64_encode((string) json_encode(['aud' => 'acma-senderid-webhook', 'sub' => 'acma', 'exp' => time() + 99999])), '+/', '-_'), '=');

        $other = new NotificationTokenIssuer('acma', 'another-long-secret-value');

        expect($this->issuer->verify("{$h}.{$forgedPayload}.{$s}"))->toBeFalse()
            ->and($this->issuer->verify('not-a-jwt'))->toBeFalse()
            ->and($other->verify($token))->toBeFalse()
            ->and($this->issuer->verifyAuthorizationHeader(null))->toBeFalse()
            ->and($this->issuer->verifyAuthorizationHeader('Basic abc'))->toBeFalse();
    });

    it('builds the AuthUri with credentials in the query string', function () {
        $uri = $this->issuer->authUri('https://app.example/acma/senderid/oauth/token');

        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);
        expect($uri)->toStartWith('https://app.example/acma/senderid/oauth/token?')
            ->and($query)->toBe(['grant_type' => 'client_credentials', 'client_id' => 'acma', 'client_secret' => WEBHOOK_SECRET]);
    });

    it('requires a reasonably long secret', function () {
        new NotificationTokenIssuer('acma', 'short');
    })->throws(InvalidArgumentException::class);

    it('requires credentials', function () {
        new NotificationTokenIssuer('', '');
    })->throws(InvalidArgumentException::class);
});

describe('notification parsing', function () {
    // Payload shape captured from the ACMA test tenant.
    it('parses real ACMA notifications', function (string $body, NotificationType $type, NotifyReason $reason) {
        $n = Notification::fromJson($body)[0];

        expect($n->type)->toBe($type)
            ->and($n->reason)->toBe($reason)
            ->and($n->objectId)->toBe('35b76150-d3be-4dab-ba8d-e468612e6d77')
            ->and($n->get('notifyreason'))->toBe($reason->value);
    })->with([
        'brand create' => ['{"Entity":{"EntityType":"Brand","EntityId":"35b76150-d3be-4dab-ba8d-e468612e6d77","NotifyReason":"Create"}}', NotificationType::Brand, NotifyReason::Create],
        'brand modify' => ['{"Entity":{"EntityType":"Brand","EntityId":"35b76150-d3be-4dab-ba8d-e468612e6d77","NotifyReason":"Modify"}}', NotificationType::Brand, NotifyReason::Modify],
        'chatbot delete' => ['{"Entity":{"EntityType":"Chatbot","EntityId":"35b76150-d3be-4dab-ba8d-e468612e6d77","NotifyReason":"Delete"}}', NotificationType::Chatbot, NotifyReason::Delete],
        'partner delete' => ['{"Entity":{"EntityType":"Partner","EntityId":"35b76150-d3be-4dab-ba8d-e468612e6d77","NotifyReason":"Delete"}}', NotificationType::Partner, NotifyReason::Delete],
    ]);

    it('exposes reason helpers', function () {
        $n = Notification::fromArray(['Entity' => ['EntityType' => 'Chatbot', 'EntityId' => 'c1', 'NotifyReason' => 'Modify']]);

        expect($n->isSenderId())->toBeTrue()
            ->and($n->isModified())->toBeTrue()
            ->and($n->isCreated())->toBeFalse()
            ->and($n->isDeleted())->toBeFalse();
    });

    it('falls back to top-level fields and object shapes', function (array $payload, NotificationType $type, string $id) {
        $n = Notification::fromArray($payload);

        expect($n->type)->toBe($type)->and($n->objectId)->toBe($id)->and($n->reason)->toBeNull();
    })->with([
        'explicit type' => [['type' => 'chatbot', 'id' => 'abc'], NotificationType::Chatbot, 'abc'],
        'chatbot shape' => [['ChatbotId' => 'c1', 'BrandId' => 'b1'], NotificationType::Chatbot, 'c1'],
        'brand shape' => [['BrandId' => 'b1', 'BrandInfo' => []], NotificationType::Brand, 'b1'],
        'partner shape' => [['partnerId' => 'p1'], NotificationType::Partner, 'p1'],
    ]);

    it('accepts a list of notifications', function () {
        $list = Notification::fromJson('[{"Entity":{"EntityType":"Brand","EntityId":"b1"}},{"Entity":{"EntityType":"Partner","EntityId":"p1"}}]');

        expect($list)->toHaveCount(2)
            ->and($list[0]->isBrand())->toBeTrue()
            ->and($list[1]->isPartner())->toBeTrue();
    });

    it('keeps unknown payloads', function () {
        $n = Notification::fromArray(['something' => 'else']);

        expect($n->type)->toBeNull()->and($n->objectId)->toBeNull()->and($n->payload)->toBe(['something' => 'else']);
    });

    it('rejects non-JSON bodies', function () {
        Notification::fromJson('nope');
    })->throws(InvalidArgumentException::class);
});
