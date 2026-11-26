<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Data\Chatbots;

use AthenaNetworks\AcmaSenderId\Data\ContactInfo;
use AthenaNetworks\AcmaSenderId\Enums\Verify;
use AthenaNetworks\AcmaSenderId\Support\Assert;
use AthenaNetworks\AcmaSenderId\Support\Payload;

/**
 * Partial update for a chatbot / Sender ID (PATCH). Only non-null fields are sent.
 *
 * After verification has begun only the contact details may be modified.
 * Changing ServiceName or BrandId of a verified chatbot requires re-verification
 * (set Verify to Complete), which may incur fees.
 */
final class ChatbotUpdate extends Payload
{
    public function __construct(
        public readonly ?ChatbotInfo $chatbotInfo = null,
        public readonly ?ContactInfo $contact = null,
        public readonly ?Verify $verify = null,
        public readonly ?string $brandId = null,
    ) {
        Assert::length('BrandId', $brandId, 1);
        $chatbotInfo?->validate('ChatbotInfo', complete: false);
        $contact?->validate('BrandContactInfo', complete: false);
        if ($verify !== null && $chatbotInfo !== null) {
            UseCaseRules::check($verify, $chatbotInfo, requireCategory: false);
        }
    }

    /**
     * Submit an existing draft for ACMA verification.
     */
    public static function submitForVerification(Verify $verify = Verify::Complete, ?ChatbotInfo $chatbotInfo = null): self
    {
        return new self(chatbotInfo: $chatbotInfo, verify: $verify);
    }

    protected function fields(): array
    {
        return [
            'ChatbotInfo' => $this->chatbotInfo,
            'BrandId' => $this->brandId,
            'BrandContactInfo' => $this->contact,
            'Verify' => $this->verify,
        ];
    }
}
