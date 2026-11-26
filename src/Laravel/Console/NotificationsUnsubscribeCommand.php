<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Laravel\Console;

use AthenaNetworks\AcmaSenderId\Exceptions\SenderIdException;
use AthenaNetworks\AcmaSenderId\SenderIdClient;
use Illuminate\Console\Command;

final class NotificationsUnsubscribeCommand extends Command
{
    protected $signature = 'senderid:notifications:unsubscribe {--force : Do not ask for confirmation}';

    protected $description = 'Delete the ACMA push notification subscription';

    public function handle(SenderIdClient $client): int
    {
        if (! $this->option('force') && ! $this->confirm('Stop receiving ACMA push notifications?')) {
            return self::FAILURE;
        }

        try {
            $client->notifications()->unsubscribe();
        } catch (SenderIdException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Subscription deleted.');

        return self::SUCCESS;
    }
}
