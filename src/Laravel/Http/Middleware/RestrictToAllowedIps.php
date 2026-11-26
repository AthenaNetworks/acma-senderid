<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Laravel\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects webhook requests from IPs outside senderid.webhook.allowed_ips (if set).
 */
final class RestrictToAllowedIps
{
    public function __construct(private readonly Config $config)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $allowed = (array) $this->config->get('senderid.webhook.allowed_ips', []);

        if ($allowed !== [] && ! IpUtils::checkIp((string) $request->ip(), $allowed)) {
            abort(403);
        }

        return $next($request);
    }
}
