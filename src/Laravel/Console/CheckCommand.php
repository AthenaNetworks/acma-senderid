<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Laravel\Console;

use AthenaNetworks\AcmaSenderId\Exceptions\SenderIdException;
use AthenaNetworks\AcmaSenderId\SenderIdClient;
use Illuminate\Console\Command;

final class CheckCommand extends Command
{
    protected $signature = 'senderid:check';

    protected $description = 'Verify ACMA Sender ID API credentials and connectivity';

    public function handle(SenderIdClient $client): int
    {
        $this->components->info('Checking ACMA Sender ID API at ' . $client->config->baseUri);

        try {
            $providers = $client->networkProviders()->list();
            $this->components->twoColumnDetail('Authentication', '<fg=green>OK</>');
            $this->components->twoColumnDetail('Network provider ID', $providers[0]->id ?? '-');
            $this->components->twoColumnDetail('Brands', (string) count($client->brands()->list()));
            $this->components->twoColumnDetail('Sender IDs', (string) count($client->senderIds()->list()));
            $this->components->twoColumnDetail('Partners', (string) count($client->partners()->list()));
            $subscription = $client->notifications()->get();
            $this->components->twoColumnDetail('Notification callback', $subscription->callbackUri ?? '<fg=yellow>not subscribed</>');
        } catch (SenderIdException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
