<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Auth;

use AthenaNetworks\AcmaSenderId\Exceptions\AuthenticationException;

interface TokenProvider
{
    /**
     * Return a valid access token, fetching a new one if needed.
     *
     * @throws AuthenticationException
     */
    public function getToken(): AccessToken;

    /**
     * Discard any cached token so the next call fetches a fresh one.
     */
    public function invalidate(): void;
}
