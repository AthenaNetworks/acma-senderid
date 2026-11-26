<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Laravel\Console;

use AthenaNetworks\AcmaSenderId\Exceptions\SenderIdException;
use AthenaNetworks\AcmaSenderId\SenderIdClient;
use Illuminate\Console\Command;

final class NotificationsShowCommand extends Command
{
    protected $signature = 'senderid:notifications:show';

    protected $description = 'Show the ACMA push notification subscription';

    public function handle(SenderIdClient $client): int
    {
        try {
            $subscription = $client->notifications()->get();
        } catch (SenderIdException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($subscription === null) {
            $this->components->warn('No push notification subscription exists.');

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail('Callback URI', $subscription->callbackUri);
        // The AuthUri query string may contain credentials: don't print it.
        $this->components->twoColumnDetail('Auth URI', (string) preg_replace('/\?.*$/', '?…', $subscription->authUri));
        $this->components->twoColumnDetail('Authentication', $subscription->authUri === $subscription->callbackUri ? 'none' : 'bearer token');
        $this->components->twoColumnDetail('Filter', $subscription->filter ? implode(', ', $subscription->filter) : 'all');

        return self::SUCCESS;
    }
}
