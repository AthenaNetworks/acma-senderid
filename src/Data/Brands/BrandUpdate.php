<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Data\Brands;

use AthenaNetworks\AcmaSenderId\Data\Address;
use AthenaNetworks\AcmaSenderId\Enums\Verify;
use AthenaNetworks\AcmaSenderId\Exceptions\InvalidArgumentException;
use AthenaNetworks\AcmaSenderId\Support\Assert;
use AthenaNetworks\AcmaSenderId\Support\Payload;

/**
 * Partial update for a brand (PATCH). Only non-null fields are sent.
 *
 * ACMA rules: RegNumber and RefNumber cannot change, the address country cannot
 * change, and StateOfIncorp/CountryOfIncorp can only be changed together.
 */
final class BrandUpdate extends Payload
{
    public function __construct(
        public readonly ?BrandInfo $brandInfo = null,
        public readonly ?Address $address = null,
        public readonly ?string $mainBusinessTN = null,
        public readonly ?Verify $verify = null,
        public readonly ?string $primaryBusinessDomain = null,
        public readonly ?string $primaryBusinessDomainType = null,
    ) {
        $brandInfo?->validate('BrandInfo', complete: false);
        $address?->validate('BrandAddress', complete: false);
        Assert::length('MainBusinessTN', $mainBusinessTN, 1, 15);
        Assert::length('PrimaryBusinessDomain', $primaryBusinessDomain, 0, 10);
        Assert::length('PrimaryBusinessDomainType', $primaryBusinessDomainType, 0, 10);

        if ($verify === Verify::Overwrite) {
            throw InvalidArgumentException::forField('Verify', 'brands only accept "not-started" or "complete"');
        }
        if ($brandInfo !== null && ($brandInfo->stateOfIncorp === null) !== ($brandInfo->countryOfIncorp === null)) {
            throw InvalidArgumentException::forField('BrandInfo', 'StateOfIncorp and CountryOfIncorp can only be changed together');
        }
    }

    protected function fields(): array
    {
        return [
            'BrandInfo' => $this->brandInfo,
            'PrimaryBusinessDomain' => $this->primaryBusinessDomain,
            'PrimaryBusinessDomainType' => $this->primaryBusinessDomainType,
            'BrandAddress' => $this->address,
            'MainBusinessTN' => $this->mainBusinessTN,
            'Verify' => $this->verify,
        ];
    }
}
