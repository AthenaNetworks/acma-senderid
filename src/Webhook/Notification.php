<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Webhook;

use AthenaNetworks\AcmaSenderId\Enums\NotificationType;
use AthenaNetworks\AcmaSenderId\Enums\VerificationStatus;
use AthenaNetworks\AcmaSenderId\Exceptions\InvalidArgumentException;
use AthenaNetworks\AcmaSenderId\Support\Attributes;

/**
 * A push notification received from ACMA at the subscription's CallbackUri.
 *
 * The ACMA spec does not document the notification body, so this class makes a
 * best effort to identify the change type and object ID and always exposes the
 * full decoded payload via $payload / get(). Notifications are not guaranteed to
 * arrive in chronological order: re-fetch the object if you need its current state.
 */
final readonly class Notification
{
    private const array TYPE_KEYS = ['Type', 'NotificationType', 'ObjectType', 'ChangeType', 'EventType', 'Entity', 'EntityType', 'ResourceType'];

    /**
     * @param array<array-key, mixed> $payload
     */
    public function __construct(
        public array $payload,
        public ?NotificationType $type,
        public ?string $objectId,
    ) {
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $type = self::detectType($payload);

        return new self($payload, $type, self::detectId($payload, $type));
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
     * Case-insensitive lookup of a top-level payload field.
     */
    public function get(string $key): mixed
    {
        return Attributes::of($this->payload)->raw($key);
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

    /**
     * The verification status carried by the notification, if present.
     */
    public function verificationStatus(): ?VerificationStatus
    {
        foreach ([$this->payload, ...$this->nested()] as $data) {
            $a = Attributes::of($data);
            foreach (['Verified', 'PartnerVerified', 'Status', 'VerificationStatus'] as $key) {
                $status = VerificationStatus::tryFrom(strtolower((string) $a->string($key)));
                if ($status !== null) {
                    return $status;
                }
            }
        }

        return null;
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    private static function detectType(array $payload): ?NotificationType
    {
        $a = Attributes::of($payload);
        foreach (self::TYPE_KEYS as $key) {
            $type = NotificationType::fromLoose($a->string($key));
            if ($type !== null) {
                return $type;
            }
        }

        // Infer from the object's shape. Chatbots carry a BrandId, so check them first.
        return match (true) {
            $a->has('ChatbotId'), $a->has('ChatbotInfo') => NotificationType::Chatbot,
            $a->has('PartnerId'), $a->has('PartnerInfo') => NotificationType::Partner,
            $a->has('BrandId'), $a->has('BrandInfo') => NotificationType::Brand,
            default => null,
        };
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    private static function detectId(array $payload, ?NotificationType $type): ?string
    {
        $a = Attributes::of($payload);
        $keys = $type === null ? [] : ["{$type->value}Id"];

        foreach ([...$keys, 'Id', 'ObjectId', 'EntityId', 'ResourceId'] as $key) {
            $id = $a->string($key);
            if ($id !== null && $id !== '') {
                return $id;
            }
        }

        return null;
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function nested(): array
    {
        $a = Attributes::of($this->payload);

        return array_values(array_filter([
            $a->array('VerificationInfo'),
            $a->array('Data'),
            $a->array('Object'),
        ]));
    }
}
