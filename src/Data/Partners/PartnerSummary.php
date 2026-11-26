<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Data\Partners;

use AthenaNetworks\AcmaSenderId\Enums\PartnerType;
use AthenaNetworks\AcmaSenderId\Enums\VerificationStatus;
use AthenaNetworks\AcmaSenderId\Support\Attributes;
use AthenaNetworks\AcmaSenderId\Support\HasAcmaStatus;
use DateTimeImmutable;

/**
 * A partner as returned in the GET /partners list (PartnerSummary).
 */
final readonly class PartnerSummary
{
    use HasAcmaStatus;

    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public string $id,
        public ?string $partnerName,
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
    public static function fromArray(array $data): self
    {
        $a = Attributes::of($data);

        return new self(
            id: $a->string('PartnerId') ?? '',
            partnerName: $a->string('PartnerName'),
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
