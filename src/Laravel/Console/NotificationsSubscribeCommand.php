<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Laravel\Console;

use AthenaNetworks\AcmaSenderId\Data\NotificationSubscription;
use AthenaNetworks\AcmaSenderId\Exceptions\SenderIdException;
use AthenaNetworks\AcmaSenderId\SenderIdClient;
use AthenaNetworks\AcmaSenderId\Webhook\NotificationTokenIssuer;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Routing\UrlGenerator;

final class NotificationsSubscribeCommand extends Command
{
    protected $signature = 'senderid:notifications:subscribe
        {--callback= : Callback URL (defaults to this app\'s notifications route)}
        {--auth-uri= : Token endpoint URL including query parameters (defaults to this app\'s token route)}
        {--filter=* : Change types to receive: Partner, Brand, Chatbot (default: config or all)}
        {--force : Do not ask for confirmation}';

    protected $description = 'Create or replace the ACMA push notification subscription';

    public function handle(SenderIdClient $client, Config $config, UrlGenerator $url): int
    {
        $callbackOption = $this->stringOption('callback');
        $authUriOption = $this->stringOption('auth-uri');

        $usingRoutes = $callbackOption === null || ($authUriOption === null && $config->get('senderid.webhook.auth') !== 'none');
        if ($usingRoutes && ! $config->get('senderid.webhook.enabled')) {
            $this->components->error('Enable senderid.webhook.enabled (ACMA_SENDERID_WEBHOOK_ENABLED=true) or pass --callback and --auth-uri.');

            return self::FAILURE;
        }

        $callback = $callbackOption ?? $url->route('senderid.webhook.notifications');

        if ($authUriOption !== null) {
            $authUri = $authUriOption;
        } elseif ($config->get('senderid.webhook.auth') === 'none') {
            $authUri = $callback;
        } else {
            try {
                $authUri = $this->laravel->make(NotificationTokenIssuer::class)->authUri($url->route('senderid.webhook.token'));
            } catch (SenderIdException $e) {
                $this->components->error($e->getMessage());

                return self::FAILURE;
            }
        }

        /** @var list<string> $filter */
        $filter = (array) $this->option('filter') ?: (array) $config->get('senderid.webhook.filter', []);

        $this->components->twoColumnDetail('Callback URI', $callback);
        $this->components->twoColumnDetail('Auth URI', (string) preg_replace('/\?.*$/', '?…', $authUri));
        $this->components->twoColumnDetail('Filter', $filter === [] ? 'all' : implode(', ', $filter));

        if (! $this->option('force') && ! $this->confirm('Replace the existing ACMA subscription with these settings?', true)) {
            return self::FAILURE;
        }

        try {
            $client->notifications()->subscribe(new NotificationSubscription($callback, $authUri, $filter === [] ? null : $filter));
        } catch (SenderIdException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Subscription saved. ACMA may take up to an hour to apply it.');

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
