<?php

declare(strict_types=1);

use AthenaNetworks\AcmaSenderId\Enums\NotificationType;
use AthenaNetworks\AcmaSenderId\Laravel\Events\BrandChanged;
use AthenaNetworks\AcmaSenderId\Laravel\Events\ChatbotChanged;
use AthenaNetworks\AcmaSenderId\Laravel\Events\NotificationReceived;
use AthenaNetworks\AcmaSenderId\Laravel\Events\PartnerChanged;
use AthenaNetworks\AcmaSenderId\Webhook\NotificationTokenIssuer;
use Illuminate\Support\Facades\Event;

const TOKEN_URL = '/acma/senderid/oauth/token';
const CALLBACK_URL = '/acma/senderid/notifications';

function webhookToken(): string
{
    return app(NotificationTokenIssuer::class)->issue('acma', 'a-very-long-webhook-secret')->token;
}

describe('token endpoint', function () {
    it('issues a token for credentials in the query string', function () {
        $response = $this->post(TOKEN_URL . '?grant_type=client_credentials&client_id=acma&client_secret=a-very-long-webhook-secret');

        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in'])
            ->assertJson(['token_type' => 'Bearer', 'expires_in' => 3600]);

        expect(app(NotificationTokenIssuer::class)->verify($response->json('access_token')))->toBeTrue();
    });

    it('accepts form body and basic auth credentials', function () {
        $this->post(TOKEN_URL, ['grant_type' => 'client_credentials', 'client_id' => 'acma', 'client_secret' => 'a-very-long-webhook-secret'])->assertOk();

        $this->withHeader('Authorization', 'Basic ' . base64_encode('acma:a-very-long-webhook-secret'))
            ->post(TOKEN_URL)
            ->assertOk();
    });

    it('rejects invalid credentials', function () {
        $this->post(TOKEN_URL . '?client_id=acma&client_secret=nope')->assertStatus(401)->assertJson(['error' => 'invalid_client']);
    });

    it('rejects other grant types', function () {
        $this->post(TOKEN_URL . '?grant_type=password&client_id=acma&client_secret=a-very-long-webhook-secret')
            ->assertStatus(400)
            ->assertJson(['error' => 'unsupported_grant_type']);
    });
});

describe('notification endpoint', function () {
    it('requires a valid bearer token', function () {
        Event::fake([NotificationReceived::class, BrandChanged::class, ChatbotChanged::class, PartnerChanged::class]);

        $this->postJson(CALLBACK_URL, ['ChatbotId' => 'c1'])->assertStatus(401);
        $this->withToken('forged')->postJson(CALLBACK_URL, ['ChatbotId' => 'c1'])->assertStatus(401);

        Event::assertNothingDispatched();
    });

    it('dispatches generic and typed events', function (array $payload, string $typedEvent) {
        Event::fake();

        $this->withToken(webhookToken())->postJson(CALLBACK_URL, $payload)->assertOk()->assertJson(['status' => 'ok']);

        Event::assertDispatched(NotificationReceived::class, fn (NotificationReceived $e) => $e->notification->payload === $payload);
        Event::assertDispatched($typedEvent);
    })->with([
        'chatbot' => [['ChatbotId' => 'c1', 'Verified' => 'complete'], ChatbotChanged::class],
        'brand' => [['Type' => 'Brand', 'Id' => 'b1'], BrandChanged::class],
        'partner' => [['PartnerId' => 'p1'], PartnerChanged::class],
    ]);

    it('dispatches only the generic event for unknown types', function () {
        Event::fake();

        $this->withToken(webhookToken())->postJson(CALLBACK_URL, ['foo' => 'bar'])->assertOk();

        Event::assertDispatched(NotificationReceived::class);
        Event::assertNotDispatched(ChatbotChanged::class);
        Event::assertNotDispatched(BrandChanged::class);
        Event::assertNotDispatched(PartnerChanged::class);
    });

    it('handles batched notifications', function () {
        Event::fake();

        $this->withToken(webhookToken())->postJson(CALLBACK_URL, [['ChatbotId' => 'c1'], ['ChatbotId' => 'c2']])->assertOk();

        Event::assertDispatchedTimes(ChatbotChanged::class, 2);
    });

    it('rejects malformed bodies', function () {
        $this->withToken(webhookToken())
            ->call('POST', CALLBACK_URL, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . webhookToken()], 'not json')
            ->assertStatus(400);
    });

    it('skips authentication when auth is none', function () {
        config()->set('senderid.webhook.auth', 'none');
        Event::fake();

        $this->postJson(CALLBACK_URL, ['ChatbotId' => 'c1'])->assertOk();

        Event::assertDispatched(ChatbotChanged::class, fn (ChatbotChanged $e) => $e->notification->type === NotificationType::Chatbot);
    });

    it('restricts source IPs when configured', function () {
        config()->set('senderid.webhook.allowed_ips', ['52.189.234.100']);

        $this->withToken(webhookToken())->postJson(CALLBACK_URL, ['ChatbotId' => 'c1'])->assertForbidden();

        $this->withToken(webhookToken())
            ->withServerVariables(['REMOTE_ADDR' => '52.189.234.100'])
            ->postJson(CALLBACK_URL, ['ChatbotId' => 'c1'])
            ->assertOk();
    });
});
