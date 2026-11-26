<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Tests\Fixtures;

use AthenaNetworks\AcmaSenderId\Config;
use AthenaNetworks\AcmaSenderId\SenderIdClient;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\SimpleCache\CacheInterface;

final class Factory
{
    public static function client(MockHttpClient $http, ?CacheInterface $cache = null): SenderIdClient
    {
        $factory = new HttpFactory();

        return new SenderIdClient(new Config('test-client', 'test-secret'), $http, $factory, $factory, $cache);
    }
}
