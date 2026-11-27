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
     * @throws ApiException UnexpectedResponseException (a ServerException) if ACMA returns no ID.
     */
    public function create(NewChatbot $chatbot): string
    {
        return Ids::created($this->transport->post($this->transport->path('chatbots'), $chatbot->toArray()), 'ChatbotId');
    }

    /**
     * Partially update a Sender ID registration and return its ID.
     *
     * ACMA currently rejects any PATCH to a chatbot that has an expiry unless
     * the expiry is re-sent ("Error in expiresOn. Invalid format."). When
     * $preserveExpiry is true and $changes does not set one, the current
     * expiry is fetched and re-sent unchanged (one extra GET request).
     *
     * @throws ApiException
     */
    public function update(string $chatbotId, ChatbotUpdate $changes, bool $preserveExpiry = true): string
    {
        if ($preserveExpiry && ! $changes->setsExpiry()) {
            $current = $this->get($chatbotId)->chatbotInfo->expiresOn();
            if ($current !== null) {
                $changes = $changes->withExpiresOn($current);
            }
        }

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
