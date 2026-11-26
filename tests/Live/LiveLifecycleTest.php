<?php

declare(strict_types=1);

/*
 * Write tests against the ACMA **test tenant**. They create, update and delete
 * brands, draft Sender IDs and telco partners, cleaning up after themselves.
 * Records are named with a "ZZ Package Test" prefix; leftovers from aborted
 * runs are removed first.
 *
 * Requires ACMA_SENDERID_CLIENT_ID / ACMA_SENDERID_CLIENT_SECRET, and
 * ACMA_SENDERID_ALLOW_WRITES=1 as a guard against running on a production tenant.
 * ACMA_SENDERID_TEST_ABN must be an ABN active on the ABR (default: the ACMA's own).
 *
 * Sender IDs are never submitted for verification: that sends emails and makes
 * the record read-only and undeletable while pending.
 */

use AthenaNetworks\AcmaSenderId\Data\Address;
use AthenaNetworks\AcmaSenderId\Data\Brands\BrandInfo;
use AthenaNetworks\AcmaSenderId\Data\Brands\BrandUpdate;
use AthenaNetworks\AcmaSenderId\Data\Brands\NewBrand;
use AthenaNetworks\AcmaSenderId\Data\Chatbots\ChatbotInfo;
use AthenaNetworks\AcmaSenderId\Data\Chatbots\ChatbotUpdate;
use AthenaNetworks\AcmaSenderId\Data\Chatbots\NewChatbot;
use AthenaNetworks\AcmaSenderId\Data\ContactInfo;
use AthenaNetworks\AcmaSenderId\Data\Partners\NewPartner;
use AthenaNetworks\AcmaSenderId\Data\Partners\PartnerInfo;
use AthenaNetworks\AcmaSenderId\Data\Partners\PartnerUpdate;
use AthenaNetworks\AcmaSenderId\Enums\PartnerType;
use AthenaNetworks\AcmaSenderId\Enums\VerificationStatus;
use AthenaNetworks\AcmaSenderId\Exceptions\NotFoundException;
use AthenaNetworks\AcmaSenderId\Exceptions\ValidationException;
use AthenaNetworks\AcmaSenderId\SenderIdClient;

const LIVE_PREFIX = 'ZZ Package Test';

function liveClient(): SenderIdClient
{
    $id = getenv('ACMA_SENDERID_CLIENT_ID');
    $secret = getenv('ACMA_SENDERID_CLIENT_SECRET');

    if (! is_string($id) || $id === '' || ! is_string($secret) || $secret === '') {
        test()->markTestSkipped('Set ACMA_SENDERID_CLIENT_ID and ACMA_SENDERID_CLIENT_SECRET to run live tests.');
    }
    if (getenv('ACMA_SENDERID_ALLOW_WRITES') !== '1') {
        test()->markTestSkipped('Set ACMA_SENDERID_ALLOW_WRITES=1 (test tenant only) to run live write tests.');
    }

    return SenderIdClient::create($id, $secret);
}

function liveCleanup(SenderIdClient $client): void
{
    foreach ($client->chatbots()->list() as $chatbot) {
        if (str_starts_with((string) $chatbot->brandName, LIVE_PREFIX) && $chatbot->verificationStatus() !== VerificationStatus::Pending) {
            $client->chatbots()->delete($chatbot->id);
        }
    }
    foreach ($client->brands()->list() as $brand) {
        if (str_starts_with((string) $brand->brandName, LIVE_PREFIX)) {
            $client->brands()->delete($brand->id);
        }
    }
    foreach ($client->partners()->list() as $partner) {
        if (str_starts_with((string) $partner->partnerName, LIVE_PREFIX)) {
            $client->partners()->delete($partner->id);
        }
    }
}

function liveBrand(SenderIdClient $client): string
{
    return $client->brands()->create(new NewBrand(
        brandInfo: BrandInfo::australian(LIVE_PREFIX . ' Brand', getenv('ACMA_SENDERID_TEST_ABN') ?: '51824753556', 'https://example.com.au'),
        address: new Address('1 Test St', 'Brisbane', '4000', 'AU', governingDistrict: 'QLD'),
        mainBusinessTN: '0730000000',
    ));
}

beforeEach(function () {
    $this->client = liveClient();
    liveCleanup($this->client);
})->group('live');

afterEach(function () {
    if (isset($this->client)) {
        liveCleanup($this->client);
    }
});

it('manages the brand lifecycle', function () {
    $id = liveBrand($this->client);

    $brand = $this->client->brands()->get($id);
    expect($brand->brandInfo->brandName)->toBe(LIVE_PREFIX . ' Brand')
        ->and($brand->brandInfo->regNumberType)->toBe('ABN')
        ->and($brand->address->governingDistrict)->toBe('QLD')
        ->and($brand->brandVerified)->toBe('complete');

    $this->client->brands()->update($id, new BrandUpdate(mainBusinessTN: '0731111111'));
    expect($this->client->brands()->get($id)->mainBusinessTN)->toBe('0731111111')
        ->and(array_map(fn ($b) => $b->id, $this->client->brands()->list()))->toContain($id);

    $this->client->brands()->delete($id);
    expect(fn () => $this->client->brands()->get($id))->toThrow(NotFoundException::class);
})->group('live');

it('manages a draft Sender ID, preserving its expiry across updates', function () {
    $brandId = liveBrand($this->client);
    $expiry = new DateTimeImmutable('+45 days 13:45:30', new DateTimeZone('UTC'));

    $id = $this->client->senderIds()->create(new NewChatbot(
        brandId: $brandId,
        chatbotInfo: new ChatbotInfo(serviceName: 'ZZPKGTEST', serviceId: 'pkg-live-test', website: 'https://example.com.au', expiresOn: $expiry),
        contact: new ContactInfo('Test', 'User', 'test@example.com.au', '0400000000'),
    ));

    $chatbot = $this->client->senderIds()->get($id);
    expect($chatbot->senderId())->toBe('ZZPKGTEST')
        ->and($chatbot->brandId)->toBe($brandId)
        ->and($chatbot->verificationStatus())->toBe(VerificationStatus::NotStarted)
        ->and($chatbot->chatbotInfo->expiresOn()?->getTimestamp())->toBe($expiry->getTimestamp());

    $summary = array_values(array_filter($this->client->senderIds()->list(), fn ($s) => $s->id === $id))[0];
    expect($summary->expiresOn?->getTimestamp())->toBe($expiry->getTimestamp());

    // Contact-only update: works because the package re-sends the expiry.
    $this->client->senderIds()->update($id, new ChatbotUpdate(contact: new ContactInfo(title: 'Manager')));
    $updated = $this->client->senderIds()->get($id);
    expect($updated->contact->title)->toBe('Manager')
        ->and($updated->chatbotInfo->expiresOn()?->getTimestamp())->toBe($expiry->getTimestamp());

    // Documents the ACMA quirk the workaround exists for. If this starts
    // passing, ACMA has fixed it and preserveExpiry can default to false.
    expect(fn () => $this->client->senderIds()->update($id, new ChatbotUpdate(new ChatbotInfo(description: 'x')), preserveExpiry: false))
        ->toThrow(ValidationException::class, 'expiresOn');

    $this->client->senderIds()->delete($id);
    expect(fn () => $this->client->senderIds()->get($id))->toThrow(NotFoundException::class);
})->group('live');

it('manages the telco partner lifecycle', function () {
    $id = $this->client->partners()->create(new NewPartner(
        partnerInfo: new PartnerInfo(LIVE_PREFIX . ' EMSP', '123456789', 'https://example.com', 'EIN', 'US'),
        address: new Address('1 Main St', 'Austin', '73301', 'US'),
        mainBusinessTN: '+15125550100',
        contact: new ContactInfo('Sam', 'Lee', 'test@example.com', '+15125550100', 'CTO'),
        type: PartnerType::TelcoPartner,
    ));

    $partner = $this->client->partners()->get($id);
    expect($partner->type())->toBe(PartnerType::TelcoPartner)
        ->and($partner->partnerInfo->regNumberType)->toBe('EIN')
        ->and($partner->contact->title)->toBe('CTO');

    $this->client->partners()->update($id, new PartnerUpdate(mainBusinessTN: '+15125550199'));
    expect($this->client->partners()->get($id)->mainBusinessTN)->toBe('+15125550199');

    $this->client->partners()->delete($id);
    expect(fn () => $this->client->partners()->get($id))->toThrow(NotFoundException::class);
})->group('live');

it('surfaces API validation errors', function () {
    try {
        $this->client->brands()->create(new NewBrand(
            brandInfo: BrandInfo::australian(LIVE_PREFIX . ' Bad ABN', '12345678901', 'https://example.com.au'),
            address: new Address('1 Test St', 'Brisbane', '4000', 'AU'),
            mainBusinessTN: '0730000000',
        ));
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors)->not->toBeEmpty()
            ->and($e->errors[0]->code)->not->toBeNull()
            ->and(implode(' ', $e->messages()))->toContain('regNumber');
    }
})->group('live');
