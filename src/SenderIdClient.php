<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId;

use AthenaNetworks\AcmaSenderId\Auth\ClientCredentialsTokenProvider;
use AthenaNetworks\AcmaSenderId\Auth\TokenProvider;
use AthenaNetworks\AcmaSenderId\Data\Brands\Brand;
use AthenaNetworks\AcmaSenderId\Data\Chatbots\Chatbot;
use AthenaNetworks\AcmaSenderId\Data\Partners\Partner;
use AthenaNetworks\AcmaSenderId\Enums\NotificationType;
use AthenaNetworks\AcmaSenderId\Exceptions\ApiException;
use AthenaNetworks\AcmaSenderId\Exceptions\NotFoundException;
use AthenaNetworks\AcmaSenderId\Http\Transport;
use AthenaNetworks\AcmaSenderId\Resources\Brands;
use AthenaNetworks\AcmaSenderId\Resources\Chatbots;
use AthenaNetworks\AcmaSenderId\Resources\NetworkProviders;
use AthenaNetworks\AcmaSenderId\Resources\Notifications;
use AthenaNetworks\AcmaSenderId\Resources\Partners;
use AthenaNetworks\AcmaSenderId\Support\Attributes;
use AthenaNetworks\AcmaSenderId\Webhook\Notification;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Client for the ACMA SMS Sender ID Register API.
 *
 * The API uses RCS Verification Authority terminology:
 *   Chatbot = Sender ID, Brand = Entity, Partner = Telco partner / Entity associate.
 *
 *     $client = SenderIdClient::create('client-id', 'client-secret');
 *     foreach ($client->senderIds()->list() as $senderId) { ... }
 */
final class SenderIdClient
{
    private readonly Transport $transport;

    private ?Brands $brands = null;

    private ?Chatbots $chatbots = null;

    private ?Partners $partners = null;

    private ?Notifications $notifications = null;

    private ?NetworkProviders $networkProviders = null;

    public function __construct(
        public readonly Config $config,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?CacheInterface $cache = null,
        ?TokenProvider $tokenProvider = null,
    ) {
        $httpClient ??= Psr18ClientDiscovery::find();
        $requestFactory ??= Psr17FactoryDiscovery::findRequestFactory();
        $streamFactory ??= Psr17FactoryDiscovery::findStreamFactory();
        $tokenProvider ??= new ClientCredentialsTokenProvider($config, $httpClient, $requestFactory, $streamFactory, $cache);

        $this->transport = new Transport($config, $tokenProvider, $httpClient, $requestFactory, $streamFactory);
    }

    /**
     * Create a client with default settings and auto-discovered PSR-18/PSR-17 implementations.
     */
    public static function create(string $clientId, #[\SensitiveParameter] string $clientSecret, ?CacheInterface $cache = null): self
    {
        return new self(new Config($clientId, $clientSecret), cache: $cache);
    }

    public function brands(): Brands
    {
        return $this->brands ??= new Brands($this->transport);
    }

    /**
     * Alias of brands(): in Sender ID terms a brand is the entity.
     */
    public function entities(): Brands
    {
        return $this->brands();
    }

    public function chatbots(): Chatbots
    {
        return $this->chatbots ??= new Chatbots($this->transport);
    }

    /**
     * Alias of chatbots(): in Sender ID terms a chatbot is a Sender ID registration.
     */
    public function senderIds(): Chatbots
    {
        return $this->chatbots();
    }

    public function partners(): Partners
    {
        return $this->partners ??= new Partners($this->transport);
    }

    public function notifications(): Notifications
    {
        return $this->notifications ??= new Notifications($this->transport);
    }

    public function networkProviders(): NetworkProviders
    {
        return $this->networkProviders ??= new NetworkProviders($this->transport);
    }

    /**
     * Whether ACMA has already approved this Sender ID for the entity with the given ABN.
     *
     * If true, a valid use case category is not required when creating the Sender ID.
     *
     * @throws ApiException
     */
    public function hasValidUseCase(string $abn, string $senderId): bool
    {
        $data = $this->transport->post($this->transport->path('acmaValidUseCaseCheck'), [
            'ABN' => preg_replace('/\s+/', '', $abn) ?? $abn,
            'ServiceName' => $senderId,
        ]);

        return Attributes::of($data)->bool('HasValidUseCase') ?? false;
    }

    /**
     * Fetch the current state of the object a push notification refers to.
     *
     * Returns null for deletions, unrecognised notifications, or objects that
     * no longer exist (notifications can arrive late and out of order).
     *
     * @throws ApiException
     */
    public function fetchNotified(Notification $notification): Brand|Chatbot|Partner|null
    {
        if ($notification->objectId === null || $notification->isDeleted()) {
            return null;
        }

        try {
            return match ($notification->type) {
                NotificationType::Brand => $this->brands()->get($notification->objectId),
                NotificationType::Chatbot => $this->chatbots()->get($notification->objectId),
                NotificationType::Partner => $this->partners()->get($notification->objectId),
                null => null,
            };
        } catch (NotFoundException) {
            return null;
        }
    }

    /**
     * Low-level access for endpoints not wrapped by this package.
     */
    public function transport(): Transport
    {
        return $this->transport;
    }
}
