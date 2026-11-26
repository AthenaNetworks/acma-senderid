<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Resources;

use AthenaNetworks\AcmaSenderId\Data\NotificationSubscription;
use AthenaNetworks\AcmaSenderId\Enums\NotificationType;
use AthenaNetworks\AcmaSenderId\Exceptions\ApiException;
use AthenaNetworks\AcmaSenderId\Exceptions\NotFoundException;
use AthenaNetworks\AcmaSenderId\Http\Transport;

/**
 * The account's push notification subscription (one per account).
 */
final class Notifications
{
    public function __construct(private readonly Transport $transport)
    {
    }

    /**
     * The current subscription, or null if none exists.
     *
     * @throws ApiException
     */
    public function get(): ?NotificationSubscription
    {
        try {
            return NotificationSubscription::fromArray($this->transport->get($this->transport->path('notification')));
        } catch (NotFoundException) {
            return null;
        }
    }

    /**
     * Create or replace the subscription. May take up to an hour to take effect.
     *
     * @throws ApiException
     */
    public function subscribe(NotificationSubscription $subscription): void
    {
        $subscription->validate();
        $this->transport->put($this->transport->path('notification'), $subscription->toArray());
    }

    /**
     * Convenience wrapper for subscribe().
     *
     * @param list<NotificationType|string>|null $filter
     *
     * @throws ApiException
     */
    public function subscribeTo(string $callbackUri, #[\SensitiveParameter] string $authUri, ?array $filter = null): void
    {
        $this->subscribe(new NotificationSubscription($callbackUri, $authUri, $filter));
    }

    /**
     * Delete the subscription. Retries of earlier notifications may still arrive briefly.
     *
     * @throws ApiException
     */
    public function unsubscribe(): void
    {
        $this->transport->delete($this->transport->path('notification'));
    }
}
