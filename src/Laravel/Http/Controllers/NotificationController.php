<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Laravel\Http\Controllers;

use AthenaNetworks\AcmaSenderId\Enums\NotificationType;
use AthenaNetworks\AcmaSenderId\Exceptions\InvalidArgumentException;
use AthenaNetworks\AcmaSenderId\Laravel\Events\BrandChanged;
use AthenaNetworks\AcmaSenderId\Laravel\Events\ChatbotChanged;
use AthenaNetworks\AcmaSenderId\Laravel\Events\NotificationReceived;
use AthenaNetworks\AcmaSenderId\Laravel\Events\PartnerChanged;
use AthenaNetworks\AcmaSenderId\Webhook\Notification;
use AthenaNetworks\AcmaSenderId\Webhook\NotificationTokenIssuer;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives ACMA push notifications (the subscription CallbackUri).
 *
 * Responds 200 once events are dispatched; ACMA retries on any non-2xx
 * response, so queue slow listeners (implement ShouldQueue).
 */
final class NotificationController
{
    public function __invoke(Request $request, Config $config, Container $container, Dispatcher $events): JsonResponse
    {
        if ($config->get('senderid.webhook.auth', 'token') !== 'none') {
            $issuer = $container->make(NotificationTokenIssuer::class);
            if (! $issuer->verifyAuthorizationHeader($request->headers->get('Authorization'))) {
                return new JsonResponse(['error' => 'invalid_token'], 401, ['WWW-Authenticate' => 'Bearer error="invalid_token"']);
            }
        }

        try {
            $notifications = Notification::fromJson($request->getContent());
        } catch (InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        foreach ($notifications as $notification) {
            $events->dispatch(new NotificationReceived($notification));

            $typed = match ($notification->type) {
                NotificationType::Brand => new BrandChanged($notification),
                NotificationType::Chatbot => new ChatbotChanged($notification),
                NotificationType::Partner => new PartnerChanged($notification),
                null => null,
            };
            if ($typed !== null) {
                $events->dispatch($typed);
            }
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
