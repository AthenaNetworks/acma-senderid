<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Resources;

use AthenaNetworks\AcmaSenderId\Data\Chatbots\Chatbot;
use AthenaNetworks\AcmaSenderId\Data\Chatbots\ChatbotSummary;
use AthenaNetworks\AcmaSenderId\Data\Chatbots\ChatbotUpdate;
use AthenaNetworks\AcmaSenderId\Data\Chatbots\NewChatbot;
use AthenaNetworks\AcmaSenderId\Enums\VerificationStatus;
use AthenaNetworks\AcmaSenderId\Exceptions\ApiException;
use AthenaNetworks\AcmaSenderId\Http\Transport;
use AthenaNetworks\AcmaSenderId\Support\Attributes;

/**
 * Chatbots: Sender ID registrations.
 */
final class Chatbots
{
    public function __construct(private readonly Transport $transport)
    {
    }

    /**
     * List Sender IDs owned by your account.
     *
     * Watch for hasEmailBounce() on the results: it means the contact's email bounced.
     *
     * @param list<VerificationStatus|string> $verified Filter: "not-started", "pending", "complete".
     * @return list<ChatbotSummary>
     *
     * @throws ApiException
     */
    public function list(array $verified = []): array
    {
        $data = $this->transport->get($this->transport->path('chatbots'), ['verified' => Filters::statuses($verified)]);

        return array_values(array_map(
            ChatbotSummary::fromArray(...),
            array_filter(Attributes::of($data)->array('Chatbots'), is_array(...)),
        ));
    }

    /**
     * @throws ApiException
     */
    public function get(string $chatbotId): Chatbot
    {
        return Chatbot::fromArray($this->transport->get($this->transport->path('chatbots', $chatbotId)), $chatbotId);
    }

    /**
     * Create a Sender ID registration and return its ID.
     *
     * @throws ApiException
     */
    public function create(NewChatbot $chatbot): string
    {
        return Ids::extract($this->transport->post($this->transport->path('chatbots'), $chatbot->toArray()), 'ChatbotId');
    }

    /**
     * Partially update a Sender ID registration and return its ID.
     *
     * @throws ApiException
     */
    public function update(string $chatbotId, ChatbotUpdate $changes): string
    {
        return Ids::extract($this->transport->patch($this->transport->path('chatbots', $chatbotId), $changes->toArray()), 'ChatbotId', $chatbotId);
    }

    /**
     * Delete a Sender ID registration. Fails while verification is pending.
     *
     * @throws ApiException
     */
    public function delete(string $chatbotId): string
    {
        return Ids::extract($this->transport->delete($this->transport->path('chatbots', $chatbotId)), 'ChatbotId', $chatbotId);
    }
}
