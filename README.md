# ACMA Sender ID API client for PHP

A PHP client for the [ACMA SMS Sender ID Register](https://www.acma.gov.au/sms-sender-id-register) API. It works on its own or with Laravel.

Originating telcos and EMSPs approved by the ACMA use this API to register, manage and track Sender IDs on behalf of entities (brands), and to receive push notifications when registrations are approved or revoked.

- OAuth 2.0 client credentials, with tokens cached in memory and optionally in a PSR-16 cache
- Works with any PSR-18 HTTP client (Guzzle, Symfony, …) via auto-discovery
- Typed request and response objects, validated against the OpenAPI spec before anything is sent
- Typed exceptions that carry ACMA's error codes and messages
- Push notification webhook: a token endpoint for ACMA, bearer verification and payload parsing
- Laravel: service provider, facade, webhook routes and events, and Artisan commands

Requires PHP 8.3+.

## Terminology

The ACMA API implements the GSMA RCS Verification Authority standard, so it uses RCS terms:

| API (RCS)   | Sender ID Register                | This package                           |
|-------------|-----------------------------------|----------------------------------------|
| **Chatbot** | Sender ID registration            | `chatbots()` / `senderIds()`           |
| **Brand**   | Entity                            | `brands()` / `entities()`              |
| **Partner** | Telco partner / Entity associate  | `partners()`                           |

## Installation

```bash
composer require athenanetworks/acma-senderid
```

You also need a PSR-18 HTTP client. Laravel apps already have Guzzle; otherwise:

```bash
composer require guzzlehttp/guzzle
```

### Credentials

In the ACMA portal, use **Get authentication details** to generate a Client ID and Client Secret. Your identity must have the **Sender ID Originating Telco** role. ACMA does not expire secrets, so you are responsible for storing and rotating them securely.

## Standalone usage

```php
use AthenaNetworks\AcmaSenderId\SenderIdClient;

$client = SenderIdClient::create($clientId, $clientSecret);

// Optionally share tokens across processes with any PSR-16 cache:
$client = SenderIdClient::create($clientId, $clientSecret, cache: $psr16Cache);
```

For full control, pass a `Config` (base URI, token URL, scope, API version) and your own PSR-18 client and PSR-17 factories:

```php
use AthenaNetworks\AcmaSenderId\Config;

$client = new SenderIdClient(
    new Config($clientId, $clientSecret),
    httpClient: $psr18Client,
    requestFactory: $psr17Factory,
    streamFactory: $psr17Factory,
    cache: $psr16Cache,
);
```

### Registering a Sender ID

```php
use AthenaNetworks\AcmaSenderId\Data\Address;
use AthenaNetworks\AcmaSenderId\Data\Brands\BrandInfo;
use AthenaNetworks\AcmaSenderId\Data\Brands\NewBrand;
use AthenaNetworks\AcmaSenderId\Data\Chatbots\ChatbotInfo;
use AthenaNetworks\AcmaSenderId\Data\Chatbots\NewChatbot;
use AthenaNetworks\AcmaSenderId\Data\ContactInfo;
use AthenaNetworks\AcmaSenderId\Enums\UseCaseCategory;
use AthenaNetworks\AcmaSenderId\Enums\Verify;

// 1. Create the entity (brand).
$brandId = $client->brands()->create(new NewBrand(
    brandInfo: BrandInfo::australian('Example Pty Ltd', '12 345 678 901', 'https://example.com.au'),
    address: new Address('1 Example St', 'Brisbane', '4000', 'AU', governingDistrict: 'QLD'),
    mainBusinessTN: '+61730000000',
));

// 2. Check whether ACMA already holds a valid use case for this entity and Sender ID.
$alreadyApproved = $client->hasValidUseCase('12345678901', 'EXAMPLE');

// 3. Create the Sender ID and submit it for verification.
$senderId = $client->senderIds()->create(new NewChatbot(
    brandId: $brandId,
    chatbotInfo: new ChatbotInfo(
        serviceName: 'EXAMPLE',              // the Sender ID text, 2-11 characters
        serviceId: 'example-alerts',         // your identifier for the service
        website: 'https://example.com.au',
        category: $alreadyApproved ? null : [UseCaseCategory::RegisteredBusinessName],
        expiresOn: new DateTimeImmutable('+12 months -1 day'),
    ),
    contact: new ContactInfo('Jo', 'Citizen', 'jo@example.com.au', '+61400000000'),
    verify: Verify::Complete,                // or Verify::NotStarted to save a draft
));
```

`Verify::NotStarted` (the default) saves a draft. `Verify::Complete` submits the Sender ID for ACMA verification, which requires the entity to have an ABN. `Verify::Overwrite` is for Certified Participating Telcos. Once submitted, a Sender ID is read-only until ACMA approves or rejects it, and **the brand may be charged fees**.

### Reading and updating

```php
use AthenaNetworks\AcmaSenderId\Data\Chatbots\ChatbotUpdate;
use AthenaNetworks\AcmaSenderId\Data\ContactInfo;
use AthenaNetworks\AcmaSenderId\Enums\VerificationStatus;

foreach ($client->senderIds()->list([VerificationStatus::Pending]) as $summary) {
    echo $summary->senderId(), ' ', $summary->verified, ' ', $summary->acmaStatusName, PHP_EOL;

    if ($summary->hasEmailBounce()) {
        // ACMA's email to the contact bounced: fix the address.
        $client->senderIds()->update($summary->id, new ChatbotUpdate(
            contact: new ContactInfo(emailAddress: 'correct@example.com.au'),
        ));
    }
}

$chatbot = $client->senderIds()->get($senderId);
$chatbot->verificationStatus();    // VerificationStatus::Pending, Complete, Failed, Expired, ...
$chatbot->raw;                     // the full decoded response

// Submit a saved draft:
$client->senderIds()->update($senderId, ChatbotUpdate::submitForVerification());
```

Update objects (`BrandUpdate`, `ChatbotUpdate`, `PartnerUpdate`) send only the fields you set.

### Sender ID expiry dates

`x-ACMA-expiresOn` behaves differently from what the spec describes. The following was observed on the ACMA test tenant, and the package handles it for you:

- **Format.** ACMA only accepts .NET round-trip format with exactly 7 fractional digits (`2027-01-25T13:45:30.0000000Z`). Date-only values, plain ISO 8601 and 3 or 6 fractional digits are all rejected. Pass a `DateTimeInterface`, or a string with or without an offset (strings without one are treated as Australia/Sydney time), and the package formats it correctly.
- **Time zone.** ACMA stores the value as Australia/Sydney local time. The detail endpoint returns it without an offset, and the list endpoint labels it `Z`, which is wrong. `expiresOn()` and `ChatbotSummary::$expiresOn` return the correct instant in `Australia/Sydney`.
- **Updates.** Once a chatbot has an expiry, ACMA rejects any PATCH that doesn't re-send it ("Error in expiresOn. Invalid format."), even contact-only changes. `chatbots()->update()` therefore fetches the current expiry and re-sends it when your update doesn't set one. That costs one extra GET; pass `preserveExpiry: false` to skip it.
- **Range.** The spec's 7-day to 12-month window isn't enforced when a draft is created, so the package doesn't validate it either.

### Partners

```php
use AthenaNetworks\AcmaSenderId\Data\Partners\NewPartner;
use AthenaNetworks\AcmaSenderId\Data\Partners\PartnerInfo;
use AthenaNetworks\AcmaSenderId\Enums\PartnerType;

$partnerId = $client->partners()->create(new NewPartner(
    partnerInfo: new PartnerInfo('Acme EMSP Inc', '12-3456789', 'https://acme.example', 'EIN', 'US'),
    address: new Address('1 Main St', 'Austin', '73301', 'US'),
    mainBusinessTN: '+15125550100',
    contact: new ContactInfo('Sam', 'Lee', 'sam@acme.example', '+15125550100', title: 'CTO'),
    type: PartnerType::TelcoPartner,
));
```

### Errors

Every exception implements `AthenaNetworks\AcmaSenderId\Exceptions\SenderIdException`.

| Exception                    | When                                                                |
|------------------------------|---------------------------------------------------------------------|
| `InvalidArgumentException`   | Input failed validation before sending (`$e->field` names the field) |
| `ValidationException`        | ACMA returned 400 (`$e->errors`, `$e->messages()`)                   |
| `AuthenticationException`    | No token could be obtained, or ACMA returned 401                    |
| `AuthorizationException`     | 403                                                                 |
| `NotFoundException`          | 404: the record doesn't exist or isn't owned by your organisation   |
| `RateLimitException`         | 429                                                                 |
| `ServerException`            | 5xx: the request may still have been applied                        |
| `UnexpectedResponseException` | A 2xx ACMA's answer couldn't be used (not JSON, or no ID on a create). Extends `ServerException`: it may have been applied |
| `ApiException`               | Base class for all of the above HTTP errors                         |
| `TransportException`         | Network, DNS or TLS failure                                         |

```php
use AthenaNetworks\AcmaSenderId\Exceptions\ValidationException;

try {
    $client->brands()->create($brand);
} catch (ValidationException $e) {
    foreach ($e->errors as $error) {
        echo "[{$error->code}] {$error->message}\n";
    }
}
```

If ACMA rejects a cached token with 401, the client fetches a fresh token and retries once.

## Push notifications

ACMA can send an HTTPS request to your **CallbackUri** whenever a Partner, Brand or Chatbot you own changes. Before each notification it requests a bearer token from your **AuthUri**, which is an OAuth client-credentials token endpoint with its parameters in the query string, and sends that token with the notification.

This package provides both sides:

```php
use AthenaNetworks\AcmaSenderId\Webhook\Notification;
use AthenaNetworks\AcmaSenderId\Webhook\NotificationTokenIssuer;

$issuer = new NotificationTokenIssuer($webhookClientId, $webhookClientSecret);

// Token endpoint (your AuthUri):
$token = $issuer->issue($_GET['client_id'] ?? null, $_GET['client_secret'] ?? null);
// respond: {"access_token": $token->token, "token_type": "Bearer", "expires_in": $issuer->ttl()}

// Callback endpoint (your CallbackUri):
if (! $issuer->verifyAuthorizationHeader($_SERVER['HTTP_AUTHORIZATION'] ?? null)) {
    http_response_code(401);
    exit;
}
foreach (Notification::fromJson(file_get_contents('php://input')) as $notification) {
    $notification->type;        // NotificationType::Chatbot / Brand / Partner
    $notification->objectId;    // the entity's UUID
    $notification->reason;      // NotifyReason::Create / Modify / Delete
    $notification->payload;     // the full decoded body

    // Notifications carry no data: fetch the current state (null for deletions).
    $object = $client->fetchNotified($notification);   // Brand|Chatbot|Partner|null
}

// Register both URLs with ACMA:
$client->notifications()->subscribeTo(
    callbackUri: 'https://example.com/acma/notifications',
    authUri: $issuer->authUri('https://example.com/acma/token'),
);
```

Things to know (observed on the ACMA test tenant, as the spec doesn't document them):

- Each notification is one small JSON object:

  ```json
  {"Entity": {"EntityType": "Chatbot", "EntityId": "5bcb167b-…", "NotifyReason": "Modify"}}
  ```

  It contains no status or object data, so call `fetchNotified()` (or `get()`) for the current state.
- When requesting a token, ACMA sends the AuthUri's query parameters as a form-encoded POST body. Both the Laravel token route and `NotificationTokenIssuer` accept that. ACMA caches the token and reuses it across notifications.
- Notifications usually arrive within a few seconds, but not necessarily in order. For example, a Sender ID's `Create` can arrive before its brand's `Create`.
- A new subscription took about 15 minutes to activate (ACMA says up to an hour). Changes made in the meantime were then delivered in one burst.
- ACMA retries any notification that doesn't get a 2xx response, so respond quickly and queue the work.
- If AuthUri and CallbackUri are the same, ACMA performs no authentication.
- Requests came from `52.189.234.100`. The full published list is in `AcmaIpAddresses::ALL`.

## Laravel

The service provider and the `SenderId` facade are auto-discovered.

```bash
php artisan vendor:publish --tag=senderid-config
```

```dotenv
ACMA_SENDERID_CLIENT_ID=...
ACMA_SENDERID_CLIENT_SECRET=...

# Optional: cache store for access tokens (default store if unset, "false" for memory only)
ACMA_SENDERID_CACHE_STORE=redis
```

```php
use AthenaNetworks\AcmaSenderId\Laravel\Facades\SenderId;
use AthenaNetworks\AcmaSenderId\SenderIdClient;

SenderId::senderIds()->list();

// or inject it
public function __construct(private SenderIdClient $senderId) {}
```

To use a specific HTTP client, bind `Psr\Http\Client\ClientInterface` in the container.

### Webhook

```dotenv
ACMA_SENDERID_WEBHOOK_ENABLED=true
ACMA_SENDERID_WEBHOOK_CLIENT_ID=acma
ACMA_SENDERID_WEBHOOK_CLIENT_SECRET=a-long-random-string   # at least 16 characters
```

This registers two routes. Neither is in the `web` middleware group, so CSRF protection doesn't apply.

| Route                                 | Name                             | Purpose                 |
|---------------------------------------|----------------------------------|-------------------------|
| `POST acma/senderid/oauth/token`      | `senderid.webhook.token`         | AuthUri: issues tokens  |
| `POST acma/senderid/notifications`    | `senderid.webhook.notifications` | CallbackUri             |

Register them with ACMA. The URLs are generated from `APP_URL`, which must be HTTPS and publicly reachable:

```bash
php artisan senderid:notifications:subscribe --filter=Chatbot
```

Listen for events:

```php
use AthenaNetworks\AcmaSenderId\Laravel\Events\ChatbotChanged;
use AthenaNetworks\AcmaSenderId\Laravel\Facades\SenderId;

Event::listen(function (ChatbotChanged $event) {
    // Queue your listener (ShouldQueue): ACMA expects a fast 2xx response.
    if ($event->notification->isDeleted()) {
        return;   // remove it locally
    }

    $chatbot = SenderId::fetchNotified($event->notification);   // Chatbot|null
    $chatbot?->verificationStatus();                           // e.g. VerificationStatus::Complete
});
```

Every notification dispatches `NotificationReceived`. It then also dispatches `ChatbotChanged`, `BrandChanged` or `PartnerChanged` when the type is recognised.

The `senderid.webhook` config also lets you change the route `prefix`, `domain` and `middleware`, set `auth` to `none`, and restrict `allowed_ips` (for example to `AcmaIpAddresses::ALL`; configure trusted proxies first).

### Artisan commands

| Command                                 | Description                                          |
|-----------------------------------------|------------------------------------------------------|
| `senderid:check`                        | Verify credentials and show account totals           |
| `senderid:list [--status=*] [--json]`   | List Sender IDs, flagging email bounces              |
| `senderid:notifications:subscribe`      | Create or replace the push notification subscription |
| `senderid:notifications:show`           | Show the current subscription                        |
| `senderid:notifications:unsubscribe`    | Delete the subscription                              |

## Testing

```bash
composer test         # unit + Laravel tests (mocked HTTP)
composer analyse      # PHPStan level 8

# Live tests against the ACMA API. Read-only by default:
ACMA_SENDERID_CLIENT_ID=... ACMA_SENDERID_CLIENT_SECRET=... composer test:live

# Write tests: create, update and delete brands, draft Sender IDs and partners.
# Use a TEST tenant only. They clean up after themselves and never submit for verification.
ACMA_SENDERID_ALLOW_WRITES=1 ACMA_SENDERID_CLIENT_ID=... ACMA_SENDERID_CLIENT_SECRET=... composer test:live
```

The write tests need an ABN that is active on the Australian Business Register, because ACMA validates brand ABNs. By default they use the ACMA's own ABN; set `ACMA_SENDERID_TEST_ABN` to use a different one.

## Compliance

This package is a technical client only. You must review and comply with the
[Telecommunications (SMS Sender ID Register) Industry Standard 2025](https://www.legislation.gov.au/F2025L01235/latest/text),
the [SMS Sender ID Register (Application, Access and Administration) Determination 2025](https://www.legislation.gov.au/F2025L01304/latest/text)
and the [SMS Sender ID Register user guides for telcos](https://www.acma.gov.au/publications/2025-10/guide/sms-sender-id-register-user-guide-telcos).

## License

MIT
