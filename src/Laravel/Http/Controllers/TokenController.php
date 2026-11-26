<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Laravel\Http\Controllers;

use AthenaNetworks\AcmaSenderId\Webhook\InvalidClientException;
use AthenaNetworks\AcmaSenderId\Webhook\NotificationTokenIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OAuth 2.0 client-credentials token endpoint used as the subscription AuthUri.
 *
 * Credentials are accepted from the query string (how ACMA sends them), the
 * form body, or HTTP Basic authentication.
 */
final class TokenController
{
    public function __invoke(Request $request, NotificationTokenIssuer $issuer): JsonResponse
    {
        $grantType = $this->input($request, 'grant_type');
        if ($grantType !== null && $grantType !== 'client_credentials') {
            return $this->error('unsupported_grant_type', 400);
        }

        $clientId = $this->input($request, 'client_id') ?? $request->getUser();
        $clientSecret = $this->input($request, 'client_secret') ?? $request->getPassword();

        try {
            $token = $issuer->issue($clientId, $clientSecret);
        } catch (InvalidClientException) {
            return $this->error('invalid_client', 401);
        }

        return new JsonResponse([
            'access_token' => $token->token,
            'token_type' => 'Bearer',
            'expires_in' => $issuer->ttl(),
        ], 200, ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
    }

    private function input(Request $request, string $key): ?string
    {
        $value = $request->request->get($key) ?? $request->query->get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function error(string $error, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $error], $status, ['Cache-Control' => 'no-store']);
    }
}
