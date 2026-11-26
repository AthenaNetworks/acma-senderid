<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Laravel\Facades;

use AthenaNetworks\AcmaSenderId\SenderIdClient;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \AthenaNetworks\AcmaSenderId\Resources\Brands brands()
 * @method static \AthenaNetworks\AcmaSenderId\Resources\Brands entities()
 * @method static \AthenaNetworks\AcmaSenderId\Resources\Chatbots chatbots()
 * @method static \AthenaNetworks\AcmaSenderId\Resources\Chatbots senderIds()
 * @method static \AthenaNetworks\AcmaSenderId\Resources\Partners partners()
 * @method static \AthenaNetworks\AcmaSenderId\Resources\Notifications notifications()
 * @method static \AthenaNetworks\AcmaSenderId\Resources\NetworkProviders networkProviders()
 * @method static bool hasValidUseCase(string $abn, string $senderId)
 * @method static \AthenaNetworks\AcmaSenderId\Data\Brands\Brand|\AthenaNetworks\AcmaSenderId\Data\Chatbots\Chatbot|\AthenaNetworks\AcmaSenderId\Data\Partners\Partner|null fetchNotified(\AthenaNetworks\AcmaSenderId\Webhook\Notification $notification)
 * @method static \AthenaNetworks\AcmaSenderId\Http\Transport transport()
 *
 * @see SenderIdClient
 */
final class SenderId extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SenderIdClient::class;
    }
}
