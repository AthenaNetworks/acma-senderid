<?php

declare(strict_types=1);

use AthenaNetworks\AcmaSenderId\Data\Address;
use AthenaNetworks\AcmaSenderId\Data\Brands\BrandInfo;
use AthenaNetworks\AcmaSenderId\Data\Brands\BrandUpdate;
use AthenaNetworks\AcmaSenderId\Data\Brands\NewBrand;
use AthenaNetworks\AcmaSenderId\Data\Chatbots\ChatbotInfo;
use AthenaNetworks\AcmaSenderId\Data\Chatbots\ChatbotUpdate;
use AthenaNetworks\AcmaSenderId\Data\Chatbots\NewChatbot;
use AthenaNetworks\AcmaSenderId\Data\ContactInfo;
use AthenaNetworks\AcmaSenderId\Data\NotificationSubscription;
use AthenaNetworks\AcmaSenderId\Data\Partners\NewPartner;
use AthenaNetworks\AcmaSenderId\Data\Partners\PartnerInfo;
use AthenaNetworks\AcmaSenderId\Enums\NotificationType;
use AthenaNetworks\AcmaSenderId\Enums\PartnerType;
use AthenaNetworks\AcmaSenderId\Enums\UseCaseCategory;
use AthenaNetworks\AcmaSenderId\Enums\VerificationStatus;
use AthenaNetworks\AcmaSenderId\Enums\Verify;
use AthenaNetworks\AcmaSenderId\Exceptions\ApiException;
use AthenaNetworks\AcmaSenderId\Exceptions\AuthenticationException;
use AthenaNetworks\AcmaSenderId\Exceptions\NotFoundException;
use AthenaNetworks\AcmaSenderId\Exceptions\ServerException;
use AthenaNetworks\AcmaSenderId\Exceptions\TransportException;
use AthenaNetworks\AcmaSenderId\Exceptions\ValidationException;
use AthenaNetworks\AcmaSenderId\Tests\Fixtures\Factory;
use AthenaNetworks\AcmaSenderId\Tests\Fixtures\MockHttpClient;
use AthenaNetworks\AcmaSenderId\Webhook\Notification;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

const BRAND_ID = '3fa85f64-5717-4562-b3fc-2c963f66afa6';
const CHATBOT_ID = '9b2e8a51-1c55-4b7e-a0ff-2f1d0e8a9c11';

beforeEach(function () {
    $this->http = new MockHttpClient();
    $this->client = Factory::client($this->http);
});

describe('requests', function () {
    it('sends authenticated JSON requests to the versioned path', function () {
        $this->http->queueJson(['Brands' => []]);

        $this->client->brands()->list();

        $request = $this->http->lastRequest();
        expect($request->getMethod())->toBe('GET')
            ->and((string) $request->getUri())->toBe('https://backend.acma.gov.au/sndr/rcsva/v1/brands')
            ->and($request->getHeaderLine('Authorization'))->toBe('Bearer token-1')
            ->and($request->getHeaderLine('Accept'))->toBe('application/json');
    });

    it('repeats the verified filter for each status', function () {
        $this->http->queueJson(['Chatbots' => []]);

        $this->client->senderIds()->list([VerificationStatus::Pending, 'complete']);

        expect($this->http->lastRequest()->getUri()->getQuery())->toBe('verified=pending&verified=complete');
    });

    it('url-encodes path segments', function () {
        $this->http->queueJson([]);

        $this->client->brands()->get('a/b');

        expect($this->http->lastRequest()->getUri()->getPath())->toBe('/sndr/rcsva/v1/brands/a%2Fb');
    });

    it('uses the unversioned network providers path', function () {
        $this->http->queueJson(['NetworkProviders' => [['NetworkProviderId' => '00000000-0000-0000-0000-000000000000', 'NetworkProviderName' => 'SMS/MMS']]]);

        $providers = $this->client->networkProviders()->list();

        expect($this->http->lastRequest()->getUri()->getPath())->toBe('/sndr/rcsva/v1/util/network_providers')
            ->and($providers[0]->name)->toBe('SMS/MMS');
    });

    it('refreshes the token and retries once on 401', function () {
        $this->http->queue(new Response(401), MockHttpClient::json(['Brands' => []]));

        $this->client->brands()->list();

        expect($this->http->requests)->toHaveCount(2)
            ->and($this->http->requests[0]->getHeaderLine('Authorization'))->toBe('Bearer token-1')
            ->and($this->http->requests[1]->getHeaderLine('Authorization'))->toBe('Bearer token-2');
    });

    it('throws AuthenticationException if the retry is also rejected', function () {
        $this->http->queue(new Response(401), new Response(401));

        $this->client->brands()->list();
    })->throws(AuthenticationException::class);
});

describe('errors', function () {
    it('maps 400 responses to ValidationException with API messages', function () {
        $this->http->queueJson([
            'messages' => [
                ['code' => '11000', 'message' => 'Error in brandName. This field requires a non-blank value.'],
                ['code' => '11000', 'message' => 'Error in website. This field requires a non-blank value.'],
            ],
            'status' => 'failure',
        ], 400);

        try {
            $this->client->brands()->delete(BRAND_ID);
            $this->fail('Expected exception');
        } catch (ValidationException $e) {
            expect($e->statusCode)->toBe(400)
                ->and($e->status)->toBe('failure')
                ->and($e->errors)->toHaveCount(2)
                ->and($e->errors[0]->code)->toBe('11000')
                ->and($e->messages())->toContain('Error in website. This field requires a non-blank value.')
                ->and($e->getMessage())->toContain('[11000] Error in brandName');
        }
    });

    it('maps 404 to NotFoundException', function () {
        $this->http->queueJson(['messages' => [['code' => '24400', 'message' => 'The entity requested was not found']], 'status' => 'failure'], 404);

        $this->client->chatbots()->get(CHATBOT_ID);
    })->throws(NotFoundException::class, 'The entity requested was not found');

    it('maps 5xx to ServerException', function () {
        $this->http->queue(new Response(503, [], 'Service Unavailable'));

        $this->client->partners()->list();
    })->throws(ServerException::class, '503');

    it('throws on non-JSON success bodies', function () {
        $this->http->queue(new Response(200, [], '<html>'));

        $this->client->brands()->list();
    })->throws(ApiException::class, 'non-JSON');

    it('reports a non-JSON success to a create as a server fault, since it may have been applied', function () {
        $this->http->queue(new Response(200, [], '<html>'));

        $this->client->senderIds()->create(new NewChatbot(
            brandId: BRAND_ID,
            chatbotInfo: new ChatbotInfo(serviceName: 'ATHENA', serviceId: 'athena-alerts', website: 'https://athena.example'),
            contact: new ContactInfo('Jo', 'Citizen', 'jo@athena.example', '+61400000000'),
            verify: Verify::Complete,
        ));
    })->throws(ServerException::class, 'non-JSON');

    it('wraps network failures', function () {
        $this->http->queue(new ConnectException('timed out', new Request('GET', 'https://x')));

        $this->client->brands()->list();
    })->throws(TransportException::class, 'timed out');
});

describe('brands', function () {
    it('creates a brand with a PascalCase body', function () {
        $this->http->queueJson(['BrandId' => BRAND_ID]);

        $id = $this->client->brands()->create(new NewBrand(
            brandInfo: BrandInfo::australian('Athena Networks', '51 824 753 556', 'https://athena.example'),
            address: new Address('1 Example St', 'Brisbane', '4000', 'au', governingDistrict: 'QLD'),
            mainBusinessTN: '+61730000000',
            verify: Verify::Complete,
        ));

        expect($id)->toBe(BRAND_ID)
            ->and($this->http->lastRequest()->getMethod())->toBe('POST')
            ->and($this->http->lastRequest()->getHeaderLine('Content-Type'))->toBe('application/json')
            ->and($this->http->lastJson())->toBe([
                'BrandInfo' => [
                    'BrandName' => 'Athena Networks',
                    'Website' => 'https://athena.example',
                    'RegNumber' => '51824753556',
                    'RegNumberType' => 'ABN',
                    'CountryOfIncorp' => 'AU',
                ],
                'BrandAddress' => [
                    'StreetAddress1' => '1 Example St',
                    'City' => 'Brisbane',
                    'GoverningDistrict' => 'QLD',
                    'PostalCode' => '4000',
                    'Country' => 'AU',
                ],
                'MainBusinessTN' => '+61730000000',
                'Verify' => 'complete',
            ]);
    });

    it('sends only changed fields on update', function () {
        $this->http->queueJson(['BrandId' => BRAND_ID]);

        $this->client->brands()->update(BRAND_ID, new BrandUpdate(brandInfo: new BrandInfo(website: 'https://new.example')));

        expect($this->http->lastRequest()->getMethod())->toBe('PATCH')
            ->and($this->http->lastJson())->toBe(['BrandInfo' => ['Website' => 'https://new.example']]);
    });

    it('hydrates brand details', function () {
        $this->http->queueJson([
            'BrandInfo' => ['BrandName' => 'Athena', 'RegNumber' => '51824753556', 'RegNumberType' => 'ABN', 'Website' => 'https://athena.example', 'CountryOfIncorp' => 'AU'],
            'BrandAddress' => ['StreetAddress1' => '1 Example St', 'City' => 'Brisbane', 'PostalCode' => '4000', 'Country' => 'AU'],
            'MainBusinessTN' => '0730000000',
            'UpdateDateTime' => '2026-11-01T10:00:00Z',
            'BrandStatus' => 'active',
            'VerificationInfo' => ['Verified' => 'complete', 'IconVerified' => 'not-started', 'BrandVerified' => 'complete'],
        ]);

        $brand = $this->client->brands()->get(BRAND_ID);

        expect($brand->id)->toBe(BRAND_ID)
            ->and($brand->brandInfo->brandName)->toBe('Athena')
            ->and($brand->address->city)->toBe('Brisbane')
            ->and($brand->status)->toBe('active')
            ->and($brand->verificationStatus())->toBe(VerificationStatus::Complete)
            ->and($brand->updatedAt?->format('Y-m-d'))->toBe('2026-11-01')
            ->and($brand->raw['MainBusinessTN'])->toBe('0730000000');
    });

    it('lists brand summaries', function () {
        $this->http->queueJson(['Brands' => [
            ['BrandId' => BRAND_ID, 'BrandName' => 'Athena', 'Verified' => 'complete', 'BrandVerified' => 'complete', 'IconVerified' => 'not-started', 'BrandStatus' => 'active'],
        ]]);

        $brands = $this->client->entities()->list();

        expect($brands)->toHaveCount(1)
            ->and($brands[0]->id)->toBe(BRAND_ID)
            ->and($brands[0]->brandName)->toBe('Athena');
    });

    it('tolerates a null collection', function () {
        $this->http->queueJson(['Brands' => null]);

        expect($this->client->brands()->list())->toBe([]);
    });
});

describe('chatbots', function () {
    it('creates a Sender ID with the default network provider', function () {
        $this->http->queueJson(['ChatbotId' => CHATBOT_ID]);

        $id = $this->client->senderIds()->create(new NewChatbot(
            brandId: BRAND_ID,
            chatbotInfo: new ChatbotInfo(
                serviceName: 'ATHENA',
                serviceId: 'athena-alerts',
                website: 'https://athena.example',
                category: [UseCaseCategory::RegisteredBusinessName],
                expiresOn: new DateTimeImmutable('2027-06-30T00:00:00+10:00'),
            ),
            contact: new ContactInfo('Jo', 'Citizen', 'jo@athena.example', '+61400000000'),
            verify: Verify::Complete,
        ));

        expect($id)->toBe(CHATBOT_ID)->and($this->http->lastJson())->toBe([
            'ChatbotInfo' => [
                'ServiceName' => 'ATHENA',
                'ServiceId' => 'athena-alerts',
                'Website' => 'https://athena.example',
                'Category' => ['registered_business_name'],
                'x-ACMA-expiresOn' => '2027-06-29T14:00:00.0000000Z',
            ],
            'BrandId' => BRAND_ID,
            'BrandContactInfo' => [
                'FirstName' => 'Jo',
                'LastName' => 'Citizen',
                'EmailAddress' => 'jo@athena.example',
                'TelephoneNumber' => '+61400000000',
            ],
            'NetworkProviderId' => '00000000-0000-0000-0000-000000000000',
            'Verify' => 'complete',
        ]);
    });

    it('submits a draft for verification', function () {
        $this->http->queueJson(['ChatbotInfo' => ['ServiceName' => 'ATHENA']])->queueJson(['ChatbotId' => CHATBOT_ID]);

        $this->client->chatbots()->update(CHATBOT_ID, ChatbotUpdate::submitForVerification());

        expect($this->http->lastJson())->toBe(['Verify' => 'complete']);
    });

    it('re-sends the current expiry on update (ACMA rejects PATCHes without it)', function () {
        $this->http
            ->queueJson(['ChatbotInfo' => ['ServiceName' => 'ATHENA', 'x-ACMA-expiresOn' => '2027-01-26T00:45:30.0000000']])
            ->queueJson(['ChatbotId' => CHATBOT_ID]);

        $this->client->chatbots()->update(CHATBOT_ID, new ChatbotUpdate(contact: new ContactInfo(title: 'Manager')));

        expect($this->http->requests[0]->getMethod())->toBe('GET')
            ->and($this->http->lastRequest()->getMethod())->toBe('PATCH')
            ->and($this->http->lastJson())->toBe([
                'ChatbotInfo' => ['x-ACMA-expiresOn' => '2027-01-25T13:45:30.0000000Z'],
                'BrandContactInfo' => ['Title' => 'Manager'],
            ]);
    });

    it('does not fetch when the update sets an expiry', function () {
        $this->http->queueJson(['ChatbotId' => CHATBOT_ID]);

        $this->client->chatbots()->update(CHATBOT_ID, new ChatbotUpdate(new ChatbotInfo(description: 'x', expiresOn: '2027-06-30')));

        expect($this->http->requests)->toHaveCount(1)
            ->and($this->http->lastJson())->toBe(['ChatbotInfo' => ['Description' => 'x', 'x-ACMA-expiresOn' => '2027-06-29T14:00:00.0000000Z']]);
    });

    it('can skip preserving the expiry', function () {
        $this->http->queueJson(['ChatbotId' => CHATBOT_ID]);

        $this->client->chatbots()->update(CHATBOT_ID, new ChatbotUpdate(new ChatbotInfo(description: 'x')), preserveExpiry: false);

        expect($this->http->requests)->toHaveCount(1)
            ->and($this->http->lastJson())->toBe(['ChatbotInfo' => ['Description' => 'x']]);
    });

    it('hydrates chatbot details including ACMA extensions', function () {
        $this->http->queueJson([
            'ChatbotInfo' => ['ServiceName' => 'ATHENA', 'ServiceId' => 'athena-alerts', 'Category' => ['registered_trade_mark', 'other'], 'x-ACMA-expiresOn' => '2027-06-30', 'VerificationLevel' => 2],
            'BrandId' => BRAND_ID,
            'BrandContactInfo' => ['FirstName' => 'Jo', 'EmailAddress' => 'jo@athena.example'],
            'NetworkProviderId' => '00000000-0000-0000-0000-000000000000',
            'Status' => 'active',
            'VerificationInfo' => [
                'Verified' => 'pending',
                'IconVerified' => 'not-started',
                'ServiceNameVerified' => 'pending',
                '2FACompleted' => 'not-started',
                'x-ACMA-statusName' => 'Awaiting entity approval - Email Bounce',
                'x-ACMA-statusDescription' => 'Email to entity bounced',
            ],
        ]);

        $chatbot = $this->client->chatbots()->get(CHATBOT_ID);

        expect($chatbot->id)->toBe(CHATBOT_ID)
            ->and($chatbot->senderId())->toBe('ATHENA')
            ->and($chatbot->chatbotInfo->categories())->toBe([UseCaseCategory::RegisteredTradeMark])
            ->and($chatbot->chatbotInfo->category)->toBe(['registered_trade_mark', 'other'])
            ->and($chatbot->chatbotInfo->expiresOn()?->format('Y-m-d'))->toBe('2027-06-30')
            ->and($chatbot->chatbotInfo->verificationLevel)->toBe(2)
            ->and($chatbot->verificationStatus())->toBe(VerificationStatus::Pending)
            ->and($chatbot->twoFactorCompleted)->toBe('not-started')
            ->and($chatbot->hasEmailBounce())->toBeTrue()
            ->and($chatbot->acmaStatusDescription)->toBe('Email to entity bounced');
    });

    it('lists chatbot summaries', function () {
        $this->http->queueJson(['Chatbots' => [[
            'ChatbotId' => CHATBOT_ID, 'ServiceName' => 'ATHENA', 'BrandId' => BRAND_ID, 'BrandName' => 'Athena',
            'Status' => 'active', 'Verified' => 'complete', 'IconVerified' => 'not-started', '2FACompleted' => 'n/a',
            'x-ACMA-expiresOn' => '2027-06-30T00:00:00Z', 'x-ACMA-statusName' => 'Registered', 'x-ACMA-statusDescription' => 'ok',
        ]]]);

        $list = $this->client->chatbots()->list();

        expect($list[0]->senderId())->toBe('ATHENA')
            ->and($list[0]->hasEmailBounce())->toBeFalse()
            ->and($list[0]->expiresOn?->format('Y-m-d'))->toBe('2027-06-30')
            ->and($list[0]->verificationStatus())->toBe(VerificationStatus::Complete);
    });
});

describe('partners', function () {
    it('creates a telco partner with query parameters', function () {
        $this->http->queueJson(['PartnerId' => 'p-1']);

        $id = $this->client->partners()->create(new NewPartner(
            partnerInfo: new PartnerInfo('Acme EMSP Inc', '12-3456789', 'https://acme.example', 'EIN', 'US'),
            address: new Address('1 Main St', 'Austin', '73301', 'US'),
            mainBusinessTN: '+15125550100',
            contact: new ContactInfo('Sam', 'Lee', 'sam@acme.example', '+15125550100', 'CTO'),
            type: PartnerType::TelcoPartner,
        ));

        $request = $this->http->lastRequest();
        expect($id)->toBe('p-1')
            ->and($request->getUri()->getQuery())->toBe('x-ACMA-partnerType=telco-partner')
            ->and($this->http->lastJson()['PartnerContactInfo']['Title'])->toBe('CTO')
            ->and($this->http->lastJson()['Verify'])->toBe('complete');
    });

    it('hydrates partner details', function () {
        $this->http->queueJson([
            'PartnerInfo' => ['PartnerName' => 'Acme'],
            'PartnerContactInfo' => ['EmailAddress' => 'a@acme.example'],
            'PartnerStatus' => 'active',
            'PartnerVerified' => 'complete',
            'x-ACMA-partnerType' => 'telco-partner',
            'x-ACMA-entityAssociatePartnerId' => null,
            'x-ACMA-statusName' => 'Verified',
        ]);

        $partner = $this->client->partners()->get('p-1');

        expect($partner->id)->toBe('p-1')
            ->and($partner->partnerInfo->partnerName)->toBe('Acme')
            ->and($partner->type())->toBe(PartnerType::TelcoPartner)
            ->and($partner->verificationStatus())->toBe(VerificationStatus::Complete);
    });
});

describe('notifications', function () {
    it('returns null when no subscription exists', function () {
        $this->http->queueJson(['messages' => [['code' => '24400', 'message' => 'The entity requested was not found']], 'status' => 'failure'], 404);

        expect($this->client->notifications()->get())->toBeNull();
    });

    it('returns the subscription', function () {
        $this->http->queueJson(['AuthUri' => 'https://a.example/token', 'CallbackUri' => 'https://a.example/cb', 'Filter' => ['Chatbot']]);

        $subscription = $this->client->notifications()->get();

        expect($subscription?->callbackUri)->toBe('https://a.example/cb')
            ->and($subscription?->types())->toBe([NotificationType::Chatbot]);
    });

    it('subscribes with PUT', function () {
        $this->http->queue(new Response(200));

        $this->client->notifications()->subscribeTo('https://a.example/cb', 'https://a.example/token?client_id=x', [NotificationType::Chatbot, 'Brand']);

        expect($this->http->lastRequest()->getMethod())->toBe('PUT')
            ->and($this->http->lastJson())->toBe([
                'AuthUri' => 'https://a.example/token?client_id=x',
                'CallbackUri' => 'https://a.example/cb',
                'Filter' => ['Chatbot', 'Brand'],
            ]);
    });

    it('rejects non-HTTPS URIs before sending', function () {
        $this->client->notifications()->subscribe(new NotificationSubscription('http://a.example/cb', 'https://a.example/token'));
    })->throws(\AthenaNetworks\AcmaSenderId\Exceptions\InvalidArgumentException::class, 'CallbackUri');

    it('unsubscribes with DELETE', function () {
        $this->http->queue(new Response(200));

        $this->client->notifications()->unsubscribe();

        expect($this->http->lastRequest()->getMethod())->toBe('DELETE')
            ->and($this->http->lastRequest()->getUri()->getPath())->toBe('/sndr/rcsva/v1/notification');
    });
});

describe('fetchNotified', function () {
    it('fetches the object a notification refers to', function () {
        $this->http->queueJson(['ChatbotInfo' => ['ServiceName' => 'ATHENA']]);

        $object = $this->client->fetchNotified(Notification::fromArray(['Entity' => ['EntityType' => 'Chatbot', 'EntityId' => CHATBOT_ID, 'NotifyReason' => 'Modify']]));

        expect($object)->toBeInstanceOf(\AthenaNetworks\AcmaSenderId\Data\Chatbots\Chatbot::class)
            ->and($object->id)->toBe(CHATBOT_ID)
            ->and($this->http->lastRequest()->getUri()->getPath())->toBe('/sndr/rcsva/v1/chatbots/' . CHATBOT_ID);
    });

    it('returns null for deletions without a request', function () {
        expect($this->client->fetchNotified(Notification::fromArray(['Entity' => ['EntityType' => 'Brand', 'EntityId' => BRAND_ID, 'NotifyReason' => 'Delete']])))->toBeNull()
            ->and($this->http->requests)->toBe([]);
    });

    it('returns null when the object has since been deleted', function () {
        $this->http->queueJson(['messages' => [['code' => '24400', 'message' => 'The entity requested was not found']]], 404);

        expect($this->client->fetchNotified(Notification::fromArray(['Entity' => ['EntityType' => 'Partner', 'EntityId' => 'p1', 'NotifyReason' => 'Create']])))->toBeNull();
    });
});

it('checks for an existing valid use case', function () {
    $this->http->queueJson(['HasValidUseCase' => true]);

    expect($this->client->hasValidUseCase('51 824 753 556', 'ATHENA'))->toBeTrue()
        ->and($this->http->lastJson())->toBe(['ABN' => '51824753556', 'ServiceName' => 'ATHENA'])
        ->and($this->http->lastRequest()->getUri()->getPath())->toBe('/sndr/rcsva/v1/acmaValidUseCaseCheck');
});
