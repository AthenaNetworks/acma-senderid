<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Data\Chatbots;

use AthenaNetworks\AcmaSenderId\Data\ContactInfo;
use AthenaNetworks\AcmaSenderId\Enums\VerificationStatus;
use AthenaNetworks\AcmaSenderId\Support\Attributes;
use AthenaNetworks\AcmaSenderId\Support\HasAcmaStatus;
use DateTimeImmutable;

/**
 * A chatbot / Sender ID registration as returned by GET /chatbots/{chatbotId} (ChatbotDetail).
 */
final readonly class Chatbot
{
    use HasAcmaStatus;

    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public string $id,
        public ChatbotInfo $chatbotInfo,
        public ?string $brandId,
        public ContactInfo $contact,
        public ?string $networkProviderId,
        public ?string $status,
        public ?string $verified,
        public ?string $iconVerified,
        public ?string $serviceNameVerified,
        public ?string $twoFactorCompleted,
        public ?string $acmaStatusName,
        public ?string $acmaStatusDescription,
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
        $v = Attributes::of($a->array('VerificationInfo'));

        return new self(
            id: $a->string('ChatbotId') ?? $id,
            chatbotInfo: ChatbotInfo::fromArray($a->array('ChatbotInfo')),
            brandId: $a->string('BrandId'),
            contact: ContactInfo::fromArray($a->array('BrandContactInfo')),
            networkProviderId: $a->string('NetworkProviderId'),
            status: $a->string('Status'),
            verified: $v->string('Verified'),
            iconVerified: $v->string('IconVerified'),
            serviceNameVerified: $v->string('ServiceNameVerified'),
            twoFactorCompleted: $v->string('2FACompleted'),
            acmaStatusName: $v->string('x-ACMA-statusName') ?? $a->string('x-ACMA-statusName'),
            acmaStatusDescription: $v->string('x-ACMA-statusDescription') ?? $a->string('x-ACMA-statusDescription'),
            updatedAt: $a->date('UpdateDateTime'),
            raw: $data,
        );
    }

    /**
     * The Sender ID text.
     */
    public function senderId(): ?string
    {
        return $this->chatbotInfo->serviceName;
    }

    public function verificationStatus(): ?VerificationStatus
    {
        return $this->verified === null ? null : VerificationStatus::tryFrom($this->verified);
    }
}
