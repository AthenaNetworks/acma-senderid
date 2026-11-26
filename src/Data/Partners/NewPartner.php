<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Data\Partners;

use AthenaNetworks\AcmaSenderId\Data\Address;
use AthenaNetworks\AcmaSenderId\Data\ContactInfo;
use AthenaNetworks\AcmaSenderId\Enums\PartnerType;
use AthenaNetworks\AcmaSenderId\Enums\Verify;
use AthenaNetworks\AcmaSenderId\Exceptions\InvalidArgumentException;
use AthenaNetworks\AcmaSenderId\Support\Assert;
use AthenaNetworks\AcmaSenderId\Support\Payload;

/**
 * Request for creating a partner (PartnerCreate body plus query parameters).
 *
 * Entity associate: RegNumberType "ABN", an active ABN, CountryOfIncorp "AU",
 * Verify "complete" (or "overwrite" for Certified Participating Telcos).
 *
 * Telco partner: the partner's national registration number/type, and Verify "complete".
 */
final class NewPartner extends Payload
{
    public function __construct(
        public readonly PartnerInfo $partnerInfo,
        public readonly Address $address,
        public readonly string $mainBusinessTN,
        public readonly ContactInfo $contact,
        public readonly PartnerType $type = PartnerType::EntityAssociate,
        public readonly Verify $verify = Verify::Complete,
        public readonly ?string $entityAssociatePartnerId = null,
        public readonly ?string $primaryBusinessDomain = null,
        public readonly ?string $primaryBusinessDomainType = null,
    ) {
        $partnerInfo->validate('PartnerInfo', complete: true);
        $address->validate('PartnerAddress', complete: true);
        $contact->validate('PartnerContactInfo', complete: true, requireTitle: true);
        Assert::required('MainBusinessTN', $mainBusinessTN);
        Assert::length('MainBusinessTN', $mainBusinessTN, 1, 15);
        Assert::length('PrimaryBusinessDomain', $primaryBusinessDomain, 0, 10);
        Assert::length('PrimaryBusinessDomainType', $primaryBusinessDomainType, 0, 20);

        if ($type === PartnerType::EntityAssociate) {
            if (strtoupper((string) $partnerInfo->regNumberType) !== 'ABN') {
                throw InvalidArgumentException::forField('PartnerInfo.RegNumberType', 'must be "ABN" for an entity associate');
            }
            if (strtoupper((string) $partnerInfo->countryOfIncorp) !== 'AU') {
                throw InvalidArgumentException::forField('PartnerInfo.CountryOfIncorp', 'must be "AU" for an entity associate');
            }
            if ($verify === Verify::NotStarted) {
                throw InvalidArgumentException::forField('Verify', 'must be "complete" or "overwrite" for an entity associate');
            }
        }

        if ($type === PartnerType::TelcoPartner && $verify !== Verify::Complete) {
            throw InvalidArgumentException::forField('Verify', 'must be "complete" for a telco partner');
        }
    }

    /**
     * Query parameters sent alongside the body.
     *
     * @return array<string, string|null>
     */
    public function queryParameters(): array
    {
        return [
            'x-ACMA-partnerType' => $this->type->value,
            'x-ACMA-entityAssociatePartnerId' => $this->entityAssociatePartnerId,
        ];
    }

    protected function fields(): array
    {
        return [
            'PartnerInfo' => $this->partnerInfo,
            'PrimaryBusinessDomain' => $this->primaryBusinessDomain,
            'PrimaryBusinessDomainType' => $this->primaryBusinessDomainType,
            'PartnerAddress' => $this->address,
            'MainBusinessTN' => $this->mainBusinessTN,
            'PartnerContactInfo' => $this->contact,
            'Verify' => $this->verify,
        ];
    }
}
