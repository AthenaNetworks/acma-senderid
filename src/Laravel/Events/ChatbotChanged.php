<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Laravel\Events;

use AthenaNetworks\AcmaSenderId\Webhook\Notification;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched (after NotificationReceived) when ACMA notifies a change to a Chatbot (Sender ID).
 *
 * Notifications may arrive out of order; re-fetch the object for its current state.
 */
class ChatbotChanged
{
    use Dispatchable;

    public function __construct(public readonly Notification $notification)
    {
    }
}
