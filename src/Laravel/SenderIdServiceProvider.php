<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Laravel;

use AthenaNetworks\AcmaSenderId\Config;
use AthenaNetworks\AcmaSenderId\Laravel\Console\CheckCommand;
use AthenaNetworks\AcmaSenderId\Laravel\Console\ListSenderIdsCommand;
use AthenaNetworks\AcmaSenderId\Laravel\Console\NotificationsShowCommand;
use AthenaNetworks\AcmaSenderId\Laravel\Console\NotificationsSubscribeCommand;
use AthenaNetworks\AcmaSenderId\Laravel\Console\NotificationsUnsubscribeCommand;
use AthenaNetworks\AcmaSenderId\Laravel\Http\Controllers\NotificationController;
use AthenaNetworks\AcmaSenderId\Laravel\Http\Controllers\TokenController;
use AthenaNetworks\AcmaSenderId\Laravel\Http\Middleware\RestrictToAllowedIps;
use AthenaNetworks\AcmaSenderId\SenderIdClient;
use AthenaNetworks\AcmaSenderId\Webhook\NotificationTokenIssuer;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\SimpleCache\CacheInterface;

final class SenderIdServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/senderid.php', 'senderid');

        $this->app->singleton(Config::class, function (Application $app): Config {
            /** @var array{client_id?: ?string, client_secret?: ?string, base_uri?: ?string, token_url?: ?string, scope?: ?string, version?: ?string} $config */
            $config = (array) $app->make(ConfigRepository::class)->get('senderid', []);

            return Config::fromArray($config);
        });

        $this->app->singleton(SenderIdClient::class, fn (Application $app): SenderIdClient => new SenderIdClient(
            config: $app->make(Config::class),
            httpClient: $app->bound(ClientInterface::class) ? $app->make(ClientInterface::class) : null,
            requestFactory: $app->bound(RequestFactoryInterface::class) ? $app->make(RequestFactoryInterface::class) : null,
            streamFactory: $app->bound(StreamFactoryInterface::class) ? $app->make(StreamFactoryInterface::class) : null,
            cache: $this->tokenCache($app),
        ));
        $this->app->alias(SenderIdClient::class, 'senderid');

        $this->app->singleton(NotificationTokenIssuer::class, fn (Application $app): NotificationTokenIssuer => new NotificationTokenIssuer(
            (string) $app->make(ConfigRepository::class)->get('senderid.webhook.client_id'),
            (string) $app->make(ConfigRepository::class)->get('senderid.webhook.client_secret'),
            (int) $app->make(ConfigRepository::class)->get('senderid.webhook.token_ttl', 3600),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/senderid.php' => $this->app->configPath('senderid.php'),
            ], 'senderid-config');

            $this->commands([
                CheckCommand::class,
                ListSenderIdsCommand::class,
                NotificationsShowCommand::class,
                NotificationsSubscribeCommand::class,
                NotificationsUnsubscribeCommand::class,
            ]);
        }

        if ($this->app->make(ConfigRepository::class)->get('senderid.webhook.enabled')) {
            $this->registerWebhookRoutes();
        }
    }

    private function registerWebhookRoutes(): void
    {
        if ($this->app instanceof CachesRoutes && $this->app->routesAreCached()) {
            return;
        }

        $config = $this->app->make(ConfigRepository::class);

        $group = Route::prefix((string) $config->get('senderid.webhook.prefix', 'acma/senderid'))
            ->middleware([...(array) $config->get('senderid.webhook.middleware', []), RestrictToAllowedIps::class])
            ->name('senderid.webhook.');

        if ($domain = $config->get('senderid.webhook.domain')) {
            $group->domain((string) $domain);
        }

        $group->group(function (): void {
            Route::match(['GET', 'POST'], 'oauth/token', TokenController::class)->name('token');
            Route::post('notifications', NotificationController::class)->name('notifications');
        });
    }

    private function tokenCache(Application $app): ?CacheInterface
    {
        $store = $app->make(ConfigRepository::class)->get('senderid.cache.store');
        if ($store === false || $store === 'false') {
            return null;
        }

        // Laravel cache repositories implement PSR-16.
        return $app->make(CacheFactory::class)->store(is_string($store) && $store !== '' ? $store : null);
    }
}
