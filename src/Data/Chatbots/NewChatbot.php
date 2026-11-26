<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Data\Chatbots;

use AthenaNetworks\AcmaSenderId\Data\ContactInfo;
use AthenaNetworks\AcmaSenderId\Data\NetworkProvider;
use AthenaNetworks\AcmaSenderId\Enums\Verify;
use AthenaNetworks\AcmaSenderId\Support\Assert;
use AthenaNetworks\AcmaSenderId\Support\Payload;

/**
 * Request body for creating a chatbot / Sender ID registration (ChatbotCreate).
 *
 * Leave $verify as NotStarted to save a draft. Complete submits it to ACMA
 * (the entity must have an ABN); Overwrite is for Certified Participating Telcos.
 * Once submitted the record is read-only until ACMA approves or rejects it.
 * The brand may be charged fees for verification.
 */
final class NewChatbot extends Payload
{
    public function __construct(
        public readonly string $brandId,
        public readonly ChatbotInfo $chatbotInfo,
        public readonly ContactInfo $contact,
        public readonly Verify $verify = Verify::NotStarted,
        public readonly string $networkProviderId = NetworkProvider::DEFAULT_ID,
    ) {
        Assert::required('BrandId', $brandId);
        Assert::required('NetworkProviderId', $networkProviderId);
        $chatbotInfo->validate('ChatbotInfo', complete: true);
        $contact->validate('BrandContactInfo', complete: true);
        UseCaseRules::check($verify, $chatbotInfo, requireCategory: $verify === Verify::Overwrite);
    }

    protected function fields(): array
    {
        return [
            'ChatbotInfo' => $this->chatbotInfo,
            'BrandId' => $this->brandId,
            'BrandContactInfo' => $this->contact,
            'NetworkProviderId' => $this->networkProviderId,
            'Verify' => $this->verify,
        ];
    }
}
