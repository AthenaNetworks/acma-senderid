<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Data\Brands;

use AthenaNetworks\AcmaSenderId\Enums\VerificationStatus;
use AthenaNetworks\AcmaSenderId\Support\Attributes;
use DateTimeImmutable;

/**
 * A brand as returned in the GET /brands list (BrandSummary).
 */
final readonly class BrandSummary
{
    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public string $id,
        public ?string $brandName,
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
    public static function fromArray(array $data): self
    {
        $a = Attributes::of($data);

        return new self(
            id: $a->string('BrandId') ?? '',
            brandName: $a->string('BrandName'),
            status: $a->string('BrandStatus'),
            verified: $a->string('Verified'),
            brandVerified: $a->string('BrandVerified'),
            iconVerified: $a->string('IconVerified'),
            updatedAt: $a->date('UpdateDateTime'),
            raw: $data,
        );
    }

    public function verificationStatus(): ?VerificationStatus
    {
        return $this->verified === null ? null : VerificationStatus::tryFrom($this->verified);
    }
}
