<?php

declare(strict_types=1);

use AthenaNetworks\AcmaSenderId\Config;
use AthenaNetworks\AcmaSenderId\Exceptions\InvalidArgumentException;

it('applies ACMA defaults', function () {
    $config = new Config('id', 'secret');

    expect($config->baseUri)->toBe('https://backend.acma.gov.au/sndr/')
        ->and($config->version)->toBe('v1')
        ->and($config->scope)->toBe('https://acmagovaub2c.onmicrosoft.com/AcmaApis/.default');
});

it('normalises base uri and version', function () {
    $config = new Config('id', 'secret', baseUri: 'https://example.test/api', version: '2');

    expect($config->baseUri)->toBe('https://example.test/api/')
        ->and($config->version)->toBe('v2');
});

it('builds from an array, ignoring empty values', function () {
    $config = Config::fromArray(['client_id' => 'id', 'client_secret' => 'secret', 'base_uri' => null, 'version' => '']);

    expect($config->baseUri)->toBe(Config::DEFAULT_BASE_URI)->and($config->version)->toBe('v1');
});

it('requires credentials', function (string $id, string $secret) {
    new Config($id, $secret);
})->with([['', 'secret'], ['id', ' ']])->throws(InvalidArgumentException::class);

it('hides the secret from var_dump', function () {
    ob_start();
    var_dump(new Config('id', 'super-secret-value'));
    expect(ob_get_clean())->not->toContain('super-secret-value');
});
