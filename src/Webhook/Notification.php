<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Webhook;

use AthenaNetworks\AcmaSenderId\Enums\NotificationType;
use AthenaNetworks\AcmaSenderId\Enums\NotifyReason;
use AthenaNetworks\AcmaSenderId\Exceptions\InvalidArgumentException;
use AthenaNetworks\AcmaSenderId\Support\Attributes;

/**
 * A push notification received from ACMA at the subscription's CallbackUri.
 *
 * Observed payload (not documented in the OpenAPI spec):
 *
 *     {"Entity": {"EntityType": "Chatbot", "EntityId": "<uuid>", "NotifyReason": "Modify"}}
 *
 * EntityType is Brand, Chatbot or Partner; NotifyReason is Create, Modify or Delete.
 * Notifications carry no object data and may arrive out of order or in bursts,
 * so re-fetch the object for its current state (see SenderIdClient::fetchNotified()).
 *
 * Parsing is tolerant: unknown shapes keep their full payload in $payload.
 */
final readonly class Notification
{
    private const array TYPE_KEYS = ['EntityType', 'Type', 'NotificationType', 'ObjectType', 'ChangeType', 'EventType', 'ResourceType'];

    private const array ID_KEYS = ['EntityId', 'Id', 'ObjectId', 'ResourceId'];

    /**
     * @param array<array-key, mixed> $payload The full decoded notification body.
     */
    public function __construct(
        public array $payload,
        public ?NotificationType $type,
        public ?string $objectId,
        public ?NotifyReason $reason = null,
    ) {
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $root = Attributes::of($payload);
        $entity = Attributes::of($root->array('Entity'));

        $type = self::detectType($entity) ?? self::detectType($root) ?? self::inferType($root);
        $id = self::detectId($entity, $type) ?? self::detectId($root, $type);
        $reason = NotifyReason::fromLoose($entity->string('NotifyReason') ?? $root->string('NotifyReason'));

        return new self($payload, $type, $id, $reason);
    }

    /**
     * Parse a request body, which may hold a single notification or a list of them.
     *
     * @return list<self>
     *
     * @throws InvalidArgumentException When the body is not a JSON object or array.
     */
    public static function fromJson(string $body): array
    {
        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Notification body must be a JSON object or array.');
        }

        if (array_is_list($decoded) && $decoded !== []) {
            return array_values(array_map(self::fromArray(...), array_filter($decoded, is_array(...))));
        }

        return [self::fromArray($decoded)];
    }

    /**
     * Case-insensitive lookup of a field, checking the "Entity" object first.
     */
    public function get(string $key): mixed
    {
        $root = Attributes::of($this->payload);
        $entity = Attributes::of($root->array('Entity'));

        return $entity->has($key) ? $entity->raw($key) : $root->raw($key);
    }

    public function isBrand(): bool
    {
        return $this->type === NotificationType::Brand;
    }

    public function isChatbot(): bool
    {
        return $this->type === NotificationType::Chatbot;
    }

    /**
     * Alias of isChatbot().
     */
    public function isSenderId(): bool
    {
        return $this->isChatbot();
    }

    public function isPartner(): bool
    {
        return $this->type === NotificationType::Partner;
    }

    public function isCreated(): bool
    {
        return $this->reason === NotifyReason::Create;
    }

    public function isModified(): bool
    {
        return $this->reason === NotifyReason::Modify;
    }

    public function isDeleted(): bool
    {
        return $this->reason === NotifyReason::Delete;
    }

    private static function detectType(Attributes $a): ?NotificationType
    {
        foreach (self::TYPE_KEYS as $key) {
            $type = NotificationType::fromLoose($a->string($key));
            if ($type !== null) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Fallback: infer the type from an object-shaped payload.
     */
    private static function inferType(Attributes $a): ?NotificationType
    {
        // Chatbots carry a BrandId, so check them first.
        return match (true) {
            $a->has('ChatbotId'), $a->has('ChatbotInfo') => NotificationType::Chatbot,
            $a->has('PartnerId'), $a->has('PartnerInfo') => NotificationType::Partner,
            $a->has('BrandId'), $a->has('BrandInfo') => NotificationType::Brand,
            default => null,
        };
    }

    private static function detectId(Attributes $a, ?NotificationType $type): ?string
    {
        $keys = $type === null ? self::ID_KEYS : [...self::ID_KEYS, "{$type->value}Id"];

        foreach ($keys as $key) {
            $id = $a->string($key);
            if ($id !== null && $id !== '') {
                return $id;
            }
        }

        return null;
    }
}
