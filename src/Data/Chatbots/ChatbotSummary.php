<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Data\Chatbots;

use AthenaNetworks\AcmaSenderId\Enums\VerificationStatus;
use AthenaNetworks\AcmaSenderId\Support\Attributes;
use AthenaNetworks\AcmaSenderId\Support\ExpiryDate;
use AthenaNetworks\AcmaSenderId\Support\HasAcmaStatus;
use DateTimeImmutable;

/**
 * A chatbot / Sender ID as returned in the GET /chatbots list (ChatbotSummary).
 */
final readonly class ChatbotSummary
{
    use HasAcmaStatus;

    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public string $id,
        public ?string $serviceName,
        public ?string $brandId,
        public ?string $brandName,
        public ?string $partnerId,
        public ?string $partnerName,
        public ?string $status,
        public ?string $verified,
        public ?string $iconVerified,
        public ?string $serviceNameVerified,
        public ?string $twoFactorCompleted,
        public ?string $acmaStatusName,
        public ?string $acmaStatusDescription,
        public ?DateTimeImmutable $expiresOn,
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
            id: $a->string('ChatbotId') ?? '',
            serviceName: $a->string('ServiceName'),
            brandId: $a->string('BrandId'),
            brandName: $a->string('BrandName'),
            partnerId: $a->string('PartnerId'),
            partnerName: $a->string('PartnerName'),
            status: $a->string('Status'),
            verified: $a->string('Verified'),
            iconVerified: $a->string('IconVerified'),
            serviceNameVerified: $a->string('ServiceNameVerified'),
            twoFactorCompleted: $a->string('2FACompleted'),
            acmaStatusName: $a->string('x-ACMA-statusName'),
            acmaStatusDescription: $a->string('x-ACMA-statusDescription'),
            // ACMA labels this "Z" but it is Australia/Sydney local time.
            expiresOn: ExpiryDate::parseResponse($a->string('x-ACMA-expiresOn')),
            updatedAt: $a->date('UpdateDateTime'),
            raw: $data,
        );
    }

    /**
     * The Sender ID text.
     */
    public function senderId(): ?string
    {
        return $this->serviceName;
    }

    public function verificationStatus(): ?VerificationStatus
    {
        return $this->verified === null ? null : VerificationStatus::tryFrom($this->verified);
    }
}
