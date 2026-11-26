<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Laravel\Events;

use AthenaNetworks\AcmaSenderId\Webhook\Notification;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched for every push notification received from ACMA.
 */
class NotificationReceived
{
    use Dispatchable;

    public function __construct(public readonly Notification $notification)
    {
    }
}
