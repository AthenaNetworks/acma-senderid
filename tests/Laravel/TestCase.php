<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Tests\Laravel;

use AthenaNetworks\AcmaSenderId\Laravel\SenderIdServiceProvider;
use AthenaNetworks\AcmaSenderId\Tests\Fixtures\MockHttpClient;
use Orchestra\Testbench\TestCase as Orchestra;
use Psr\Http\Client\ClientInterface;

abstract class TestCase extends Orchestra
{
    protected MockHttpClient $http;

    protected function getPackageProviders($app): array
    {
        return [SenderIdServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['SenderId' => \AthenaNetworks\AcmaSenderId\Laravel\Facades\SenderId::class];
    }

    protected function defineEnvironment($app): void
    {
        $this->http = new MockHttpClient();
        $app->instance(ClientInterface::class, $this->http);

        $app['config']->set('app.url', 'https://app.example');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('senderid.client_id', 'laravel-client');
        $app['config']->set('senderid.client_secret', 'laravel-secret');
        $app['config']->set('senderid.webhook.enabled', true);
        $app['config']->set('senderid.webhook.client_id', 'acma');
        $app['config']->set('senderid.webhook.client_secret', 'a-very-long-webhook-secret');
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Console commands generate URLs from app.url; mirror that in tests.
        $this->app['url']->forceRootUrl('https://app.example');
        $this->app['url']->forceScheme('https');
    }
}
