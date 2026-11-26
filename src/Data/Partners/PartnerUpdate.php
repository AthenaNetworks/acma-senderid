<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Data\Partners;

use AthenaNetworks\AcmaSenderId\Data\Address;
use AthenaNetworks\AcmaSenderId\Data\ContactInfo;
use AthenaNetworks\AcmaSenderId\Enums\Verify;
use AthenaNetworks\AcmaSenderId\Support\Assert;
use AthenaNetworks\AcmaSenderId\Support\Payload;

/**
 * Partial update for a partner (PATCH). Only non-null fields are sent.
 */
final class PartnerUpdate extends Payload
{
    public function __construct(
        public readonly ?PartnerInfo $partnerInfo = null,
        public readonly ?Address $address = null,
        public readonly ?string $mainBusinessTN = null,
        public readonly ?ContactInfo $contact = null,
        public readonly ?Verify $verify = null,
        public readonly ?string $primaryBusinessDomain = null,
        public readonly ?string $primaryBusinessDomainType = null,
    ) {
        $partnerInfo?->validate('PartnerInfo', complete: false);
        $address?->validate('PartnerAddress', complete: false);
        $contact?->validate('PartnerContactInfo', complete: false);
        Assert::length('MainBusinessTN', $mainBusinessTN, 0, 15);
        Assert::length('PrimaryBusinessDomain', $primaryBusinessDomain, 0, 10);
        Assert::length('PrimaryBusinessDomainType', $primaryBusinessDomainType, 0, 20);
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
