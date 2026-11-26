<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Data\Brands;

use AthenaNetworks\AcmaSenderId\Data\Address;
use AthenaNetworks\AcmaSenderId\Enums\VerificationStatus;
use AthenaNetworks\AcmaSenderId\Support\Attributes;
use DateTimeImmutable;

/**
 * A brand as returned by GET /brands/{brandId} (BrandDetail).
 */
final readonly class Brand
{
    /**
     * @param array<array-key, mixed> $raw The decoded response body.
     */
    public function __construct(
        public string $id,
        public BrandInfo $brandInfo,
        public Address $address,
        public ?string $mainBusinessTN,
        public ?string $primaryBusinessDomain,
        public ?string $primaryBusinessDomainType,
        public ?string $status,
        public ?string $verified,
        public ?string $brandVerified,
        public ?string $iconVerified,
        public ?DateTimeImmutable $updatedAt,
        public array $raw = [],
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data, string $id = ''): self
    {
        $a = Attributes::of($data);
        $verification = Attributes::of($a->array('VerificationInfo'));

        return new self(
            id: $a->string('BrandId') ?? $id,
            brandInfo: BrandInfo::fromArray($a->array('BrandInfo')),
            address: Address::fromArray($a->array('BrandAddress')),
            mainBusinessTN: $a->string('MainBusinessTN'),
            primaryBusinessDomain: $a->string('PrimaryBusinessDomain'),
            primaryBusinessDomainType: $a->string('PrimaryBusinessDomainType'),
            status: $a->string('BrandStatus'),
            verified: $verification->string('Verified'),
            brandVerified: $verification->string('BrandVerified'),
            iconVerified: $verification->string('IconVerified'),
            updatedAt: $a->date('UpdateDateTime'),
            raw: $data,
        );
    }

    public function verificationStatus(): ?VerificationStatus
    {
        return $this->verified === null ? null : VerificationStatus::tryFrom($this->verified);
    }
}
