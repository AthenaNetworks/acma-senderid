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
use AthenaNetworks\AcmaSenderId\Data\Partners\NewPartner;
use AthenaNetworks\AcmaSenderId\Data\Partners\PartnerInfo;
use AthenaNetworks\AcmaSenderId\Enums\PartnerType;
use AthenaNetworks\AcmaSenderId\Enums\UseCaseCategory;
use AthenaNetworks\AcmaSenderId\Enums\Verify;
use AthenaNetworks\AcmaSenderId\Exceptions\InvalidArgumentException;

function validAddress(): Address
{
    return new Address('1 Example St', 'Brisbane', '4000', 'AU');
}

function validContact(?string $title = null): ContactInfo
{
    return new ContactInfo('Jo', 'Citizen', 'jo@athena.example', '+61400000000', $title);
}

function validChatbotInfo(array $overrides = []): ChatbotInfo
{
    return new ChatbotInfo(...array_merge(['serviceName' => 'ATHENA', 'serviceId' => 'svc', 'website' => 'https://athena.example'], $overrides));
}

function expectInvalid(callable $fn, string $field): void
{
    try {
        $fn();
    } catch (InvalidArgumentException $e) {
        expect($e->field)->toBe($field);

        return;
    }

    test()->fail("Expected InvalidArgumentException for {$field}");
}

describe('brands', function () {
    it('requires brand info fields', function () {
        expectInvalid(fn () => new NewBrand(new BrandInfo(brandName: 'X'), validAddress(), '0730000000'), 'BrandInfo.RegNumber');
    });

    it('requires address fields', function () {
        expectInvalid(fn () => new NewBrand(BrandInfo::australian('X', '1', 'https://x.example'), new Address('1 St'), '0730000000'), 'BrandAddress.City');
    });

    it('enforces maximum lengths', function () {
        expectInvalid(fn () => new NewBrand(BrandInfo::australian(str_repeat('a', 81), '1', 'https://x.example'), validAddress(), '07'), 'BrandInfo.BrandName');
        expectInvalid(fn () => new NewBrand(BrandInfo::australian('X', '1', 'https://x.example'), validAddress(), str_repeat('1', 16)), 'MainBusinessTN');
    });

    it('requires an https website', function () {
        expectInvalid(fn () => new NewBrand(BrandInfo::australian('X', '1', 'http://x.example'), validAddress(), '07'), 'BrandInfo.Website');
        expectInvalid(fn () => new NewBrand(BrandInfo::australian('X', '1', 'www.x.example'), validAddress(), '07'), 'BrandInfo.Website');
    });

    it('requires ISO country codes', function () {
        expectInvalid(fn () => new NewBrand(BrandInfo::australian('X', '1', 'https://x.example'), new Address('1 St', 'Bris', '4000', 'AUS'), '07'), 'BrandAddress.Country');
    });

    it('rejects overwrite for brands', function () {
        expectInvalid(fn () => new NewBrand(BrandInfo::australian('X', '1', 'https://x.example'), validAddress(), '07', Verify::Overwrite), 'Verify');
    });

    it('counts multibyte characters correctly', function () {
        $brand = new NewBrand(BrandInfo::australian(str_repeat('é', 80), '1', 'https://x.example'), validAddress(), '07');

        expect($brand->brandInfo->brandName)->toHaveLength(80);
    });

    it('requires state and country of incorporation to change together', function () {
        expectInvalid(fn () => new BrandUpdate(new BrandInfo(stateOfIncorp: 'QLD')), 'BrandInfo');

        expect((new BrandUpdate(new BrandInfo(countryOfIncorp: 'au', stateOfIncorp: 'QLD')))->toArray())
            ->toBe(['BrandInfo' => ['CountryOfIncorp' => 'AU', 'StateOfIncorp' => 'QLD']]);
    });

    it('omits empty nested objects from updates', function () {
        expect((new BrandUpdate(new BrandInfo(), mainBusinessTN: '0730000000'))->toArray())->toBe(['MainBusinessTN' => '0730000000']);
    });
});

describe('chatbots', function () {
    it('requires service name, service id and website', function () {
        expectInvalid(fn () => new NewChatbot('b', new ChatbotInfo(serviceName: 'ATHENA', website: 'https://x.example'), validContact()), 'ChatbotInfo.ServiceId');
    });

    it('limits the Sender ID to 2-11 characters', function (string $name) {
        expectInvalid(fn () => new NewChatbot('b', validChatbotInfo(['serviceName' => $name]), validContact()), 'ChatbotInfo.ServiceName');
    })->with(['A', 'TWELVECHARSX']);

    it('requires contact name, email and phone', function () {
        expectInvalid(fn () => new NewChatbot('b', validChatbotInfo(), new ContactInfo('Jo', 'Citizen', 'jo@athena.example')), 'BrandContactInfo.TelephoneNumber');
    });

    it('validates the contact email', function () {
        expectInvalid(fn () => new NewChatbot('b', validChatbotInfo(), new ContactInfo('Jo', 'C', 'not-an-email', '04')), 'BrandContactInfo.EmailAddress');
    });

    it('limits categories to 50', function () {
        expectInvalid(fn () => new NewChatbot('b', validChatbotInfo(['category' => array_fill(0, 51, 'exception')]), validContact()), 'ChatbotInfo.Category');
    });

    it('requires an overwrite category when overwriting', function () {
        expectInvalid(fn () => new NewChatbot('b', validChatbotInfo(), validContact(), Verify::Overwrite), 'ChatbotInfo.Category');
        expectInvalid(fn () => new NewChatbot('b', validChatbotInfo(['category' => [UseCaseCategory::RegisteredBusinessName]]), validContact(), Verify::Overwrite), 'ChatbotInfo.Category');

        $chatbot = new NewChatbot('b', validChatbotInfo(['category' => [UseCaseCategory::NationalRegistry]]), validContact(), Verify::Overwrite);
        expect($chatbot->toArray()['Verify'])->toBe('overwrite');
    });

    it('allows complete without categories when a valid use case already exists', function () {
        expect((new NewChatbot('b', validChatbotInfo(), validContact(), Verify::Complete))->toArray()['Verify'])->toBe('complete');
    });

    it('rejects categories that do not satisfy complete', function () {
        expectInvalid(fn () => new NewChatbot('b', validChatbotInfo(['category' => ['trademark_register']]), validContact(), Verify::Complete), 'ChatbotInfo.Category');
    });

    it('does not check categories for drafts', function () {
        expect((new NewChatbot('b', validChatbotInfo(['category' => ['anything']]), validContact()))->toArray()['Verify'])->toBe('not-started');
    });

    it('validates expiry date strings', function () {
        expectInvalid(fn () => new NewChatbot('b', validChatbotInfo(['expiresOn' => 'next tuesday-ish!']), validContact()), 'ChatbotInfo.x-ACMA-expiresOn');

        expect((new NewChatbot('b', validChatbotInfo(['expiresOn' => '2027-01-31']), validContact()))->toArray()['ChatbotInfo']['x-ACMA-expiresOn'])->toBe('2027-01-30T13:00:00.0000000Z');
    });

    it('validates partial updates without requiring fields', function () {
        expect((new ChatbotUpdate(contact: new ContactInfo(emailAddress: 'new@athena.example')))->toArray())
            ->toBe(['BrandContactInfo' => ['EmailAddress' => 'new@athena.example']]);

        expectInvalid(fn () => new ChatbotUpdate(new ChatbotInfo(serviceName: 'X')), 'ChatbotInfo.ServiceName');
    });
});

describe('partners', function () {
    it('requires ABN and AU for entity associates', function () {
        expectInvalid(fn () => new NewPartner(new PartnerInfo('Acme', '1', 'https://a.example', 'EIN', 'US'), validAddress(), '07', validContact('CEO')), 'PartnerInfo.RegNumberType');
        expectInvalid(fn () => new NewPartner(new PartnerInfo('Acme', '1', 'https://a.example', 'ABN', 'NZ'), validAddress(), '07', validContact('CEO')), 'PartnerInfo.CountryOfIncorp');
    });

    it('requires a contact title', function () {
        expectInvalid(fn () => new NewPartner(PartnerInfo::australian('Acme', '1', 'https://a.example'), validAddress(), '07', validContact()), 'PartnerContactInfo.Title');
    });

    it('requires verify for partners', function () {
        expectInvalid(fn () => new NewPartner(PartnerInfo::australian('Acme', '1', 'https://a.example'), validAddress(), '07', validContact('CEO'), verify: Verify::NotStarted), 'Verify');
        expectInvalid(fn () => new NewPartner(PartnerInfo::australian('Acme', '1', 'https://a.example'), validAddress(), '07', validContact('CEO'), PartnerType::TelcoPartner, Verify::Overwrite), 'Verify');
    });

    it('builds query parameters', function () {
        $partner = new NewPartner(PartnerInfo::australian('Acme', '51 824 753 556', 'https://a.example'), validAddress(), '07', validContact('CEO'), entityAssociatePartnerId: 'telco-1');

        expect($partner->queryParameters())->toBe(['x-ACMA-partnerType' => 'entity-associate', 'x-ACMA-entityAssociatePartnerId' => 'telco-1'])
            ->and($partner->partnerInfo->regNumber)->toBe('51824753556');
    });
});
