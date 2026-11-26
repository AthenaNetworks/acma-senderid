<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Data\Partners;

use AthenaNetworks\AcmaSenderId\Data\Address;
use AthenaNetworks\AcmaSenderId\Data\ContactInfo;
use AthenaNetworks\AcmaSenderId\Enums\PartnerType;
use AthenaNetworks\AcmaSenderId\Enums\VerificationStatus;
use AthenaNetworks\AcmaSenderId\Support\Attributes;
use AthenaNetworks\AcmaSenderId\Support\HasAcmaStatus;
use DateTimeImmutable;

/**
 * A partner as returned by GET /partners/{partnerId} (PartnerDetail).
 */
final readonly class Partner
{
    use HasAcmaStatus;

    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public string $id,
        public PartnerInfo $partnerInfo,
        public Address $address,
        public ?string $mainBusinessTN,
        public ContactInfo $contact,
        public ?string $primaryBusinessDomain,
        public ?string $primaryBusinessDomainType,
        public ?string $status,
        public ?string $verified,
        public ?string $partnerType,
        public ?string $entityAssociatePartnerId,
        public ?string $acmaStatusName,
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

        return new self(
            id: $a->string('PartnerId') ?? $id,
            partnerInfo: PartnerInfo::fromArray($a->array('PartnerInfo')),
            address: Address::fromArray($a->array('PartnerAddress')),
            mainBusinessTN: $a->string('MainBusinessTN'),
            contact: ContactInfo::fromArray($a->array('PartnerContactInfo')),
            primaryBusinessDomain: $a->string('PrimaryBusinessDomain'),
            primaryBusinessDomainType: $a->string('PrimaryBusinessDomainType'),
            status: $a->string('PartnerStatus'),
            verified: $a->string('PartnerVerified'),
            partnerType: $a->string('x-ACMA-partnerType'),
            entityAssociatePartnerId: $a->string('x-ACMA-entityAssociatePartnerId'),
            acmaStatusName: $a->string('x-ACMA-statusName'),
            updatedAt: $a->date('UpdateDateTime'),
            raw: $data,
        );
    }

    public function type(): ?PartnerType
    {
        return $this->partnerType === null ? null : PartnerType::tryFrom($this->partnerType);
    }

    public function verificationStatus(): ?VerificationStatus
    {
        return $this->verified === null ? null : VerificationStatus::tryFrom($this->verified);
    }
}
