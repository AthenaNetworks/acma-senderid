<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Data\Chatbots;

use AthenaNetworks\AcmaSenderId\Enums\UseCaseCategory;
use AthenaNetworks\AcmaSenderId\Exceptions\InvalidArgumentException;
use AthenaNetworks\AcmaSenderId\Support\Assert;
use AthenaNetworks\AcmaSenderId\Support\Attributes;
use AthenaNetworks\AcmaSenderId\Support\Payload;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Details of a chatbot, i.e. a Sender ID registration.
 *
 * When creating, ServiceName (the Sender ID text), ServiceId and Website are required.
 */
final class ChatbotInfo extends Payload
{
    /** @var list<string>|null */
    public readonly ?array $category;

    /**
     * @param string|null $serviceName The Sender ID text (2-11 characters).
     * @param string|null $serviceId Your identifier for the service (1-128 characters).
     * @param string|null $website Brand domain, must be an https:// URL.
     * @param list<UseCaseCategory|string>|null $category Valid use case categories (max 50).
     * @param DateTimeInterface|string|null $expiresOn When the Sender ID expires: 7 days to 12 months from now (x-ACMA-expiresOn).
     * @param int|null $verificationLevel Read-only; only present on responses.
     */
    public function __construct(
        public readonly ?string $serviceName = null,
        public readonly ?string $serviceId = null,
        public readonly ?string $website = null,
        ?array $category = null,
        public readonly DateTimeInterface|string|null $expiresOn = null,
        public readonly ?string $description = null,
        public readonly ?string $partnerId = null,
        public readonly ?string $smsFallbackNo = null,
        public readonly ?string $serviceIcon = null,
        public readonly ?string $serviceIconSN = null,
        public readonly ?string $snJurisdiction = null,
        public readonly ?string $serviceIconOwner = null,
        public readonly ?string $capacityProfile = null,
        public readonly ?string $chatbotType = null,
        public readonly ?int $verificationLevel = null,
    ) {
        $this->category = $category === null
            ? null
            : array_map(static fn (UseCaseCategory|string $c): string => $c instanceof UseCaseCategory ? $c->value : $c, $category);
    }

    /**
     * @return list<UseCaseCategory> Recognised categories (unknown values are skipped).
     */
    public function categories(): array
    {
        return array_values(array_filter(array_map(UseCaseCategory::tryFrom(...), $this->category ?? [])));
    }

    public function expiresOn(): ?DateTimeImmutable
    {
        if ($this->expiresOn instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($this->expiresOn);
        }
        if ($this->expiresOn === null || $this->expiresOn === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($this->expiresOn);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @internal
     */
    public function validate(string $prefix, bool $complete): void
    {
        if ($complete) {
            Assert::required("{$prefix}.ServiceName", $this->serviceName);
            Assert::required("{$prefix}.ServiceId", $this->serviceId);
            Assert::required("{$prefix}.Website", $this->website);
        }

        Assert::length("{$prefix}.ServiceName", $this->serviceName, 2, 11);
        Assert::length("{$prefix}.ServiceId", $this->serviceId, 1, 128);
        Assert::length("{$prefix}.Website", $this->website, 5, 128);
        Assert::httpsUrl("{$prefix}.Website", $this->website);
        Assert::length("{$prefix}.Description", $this->description, 0, 500);
        Assert::length("{$prefix}.PartnerId", $this->partnerId, 0, 128);
        Assert::length("{$prefix}.SMSFallbackNo", $this->smsFallbackNo, 0, 15);
        Assert::maxItems("{$prefix}.Category", $this->category, 50);
        Assert::length("{$prefix}.ServiceIconSN", $this->serviceIconSN, 0, 8);
        Assert::length("{$prefix}.SNJurisdiction", $this->snJurisdiction, 0, 2);
        Assert::length("{$prefix}.ServiceIconOwner", $this->serviceIconOwner, 0, 83);
        Assert::length("{$prefix}.CapacityProfile", $this->capacityProfile, 0, 128);
        Assert::length("{$prefix}.ChatbotType", $this->chatbotType, 0, 8);

        if (is_string($this->expiresOn) && $this->expiresOn !== '' && $this->expiresOn() === null) {
            throw InvalidArgumentException::forField("{$prefix}.x-ACMA-expiresOn", "must be an ISO 8601 date (got \"{$this->expiresOn}\")");
        }
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $a = Attributes::of($data);

        return new self(
            serviceName: $a->string('ServiceName'),
            serviceId: $a->string('ServiceId'),
            website: $a->string('Website'),
            category: $a->has('Category') ? $a->strings('Category') : null,
            expiresOn: $a->string('x-ACMA-expiresOn'),
            description: $a->string('Description'),
            partnerId: $a->string('PartnerId'),
            smsFallbackNo: $a->string('SMSFallbackNo'),
            serviceIcon: $a->string('ServiceIcon'),
            serviceIconSN: $a->string('ServiceIconSN'),
            snJurisdiction: $a->string('SNJurisdiction'),
            serviceIconOwner: $a->string('ServiceIconOwner'),
            capacityProfile: $a->string('CapacityProfile'),
            chatbotType: $a->string('ChatbotType'),
            verificationLevel: $a->int('VerificationLevel'),
        );
    }

    protected function fields(): array
    {
        return [
            'ServiceName' => $this->serviceName,
            'ServiceId' => $this->serviceId,
            'PartnerId' => $this->partnerId,
            'Website' => $this->website,
            'Description' => $this->description,
            'SMSFallbackNo' => $this->smsFallbackNo,
            'Category' => $this->category,
            'ServiceIcon' => $this->serviceIcon,
            'ServiceIconSN' => $this->serviceIconSN,
            'SNJurisdiction' => $this->snJurisdiction,
            'ServiceIconOwner' => $this->serviceIconOwner,
            'CapacityProfile' => $this->capacityProfile,
            'ChatbotType' => $this->chatbotType,
            'x-ACMA-expiresOn' => $this->expiresOn,
        ];
    }
}
