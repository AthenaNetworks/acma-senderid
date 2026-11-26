<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Data\Brands;

use AthenaNetworks\AcmaSenderId\Data\Address;
use AthenaNetworks\AcmaSenderId\Enums\Verify;
use AthenaNetworks\AcmaSenderId\Exceptions\InvalidArgumentException;
use AthenaNetworks\AcmaSenderId\Support\Assert;
use AthenaNetworks\AcmaSenderId\Support\Payload;

/**
 * Request body for creating a brand (BrandCreate).
 *
 * Brands are not verified by ACMA; their Verified status is set to "complete"
 * on creation. Verify may be "not-started" or "complete" with no difference in behaviour.
 */
final class NewBrand extends Payload
{
    public function __construct(
        public readonly BrandInfo $brandInfo,
        public readonly Address $address,
        public readonly string $mainBusinessTN,
        public readonly ?Verify $verify = null,
        public readonly ?string $primaryBusinessDomain = null,
        public readonly ?string $primaryBusinessDomainType = null,
    ) {
        $brandInfo->validate('BrandInfo', complete: true);
        $address->validate('BrandAddress', complete: true);
        Assert::required('MainBusinessTN', $mainBusinessTN);
        Assert::length('MainBusinessTN', $mainBusinessTN, 1, 15);
        Assert::length('PrimaryBusinessDomain', $primaryBusinessDomain, 0, 10);
        Assert::length('PrimaryBusinessDomainType', $primaryBusinessDomainType, 0, 10);

        if ($verify === Verify::Overwrite) {
            throw InvalidArgumentException::forField('Verify', 'brands only accept "not-started" or "complete"');
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
