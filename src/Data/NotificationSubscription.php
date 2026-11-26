<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Data;

use AthenaNetworks\AcmaSenderId\Enums\NotificationType;
use AthenaNetworks\AcmaSenderId\Support\Assert;
use AthenaNetworks\AcmaSenderId\Support\Attributes;
use AthenaNetworks\AcmaSenderId\Support\Payload;

/**
 * The account's push notification subscription.
 *
 * ACMA sends an HTTPS request to $callbackUri whenever a Partner, Brand or
 * Chatbot owned by the account changes. Before each notification ACMA requests
 * a bearer token from $authUri (an OAuth token endpoint, with any parameters in
 * the query string) and sends it with the notification. If both URIs are the
 * same, no authentication is performed.
 *
 * Changes can take up to an hour to take effect.
 */
final class NotificationSubscription extends Payload
{
    /** @var list<string>|null */
    public readonly ?array $filter;

    /**
     * @param list<NotificationType|string>|null $filter Change types to receive (null/empty = all).
     */
    public function __construct(
        public readonly string $callbackUri,
        #[\SensitiveParameter]
        public readonly string $authUri,
        ?array $filter = null,
    ) {
        $this->filter = $filter === null
            ? null
            : array_values(array_map(static fn (NotificationType|string $t): string => $t instanceof NotificationType ? $t->value : $t, $filter));
    }

    /**
     * @internal
     */
    public function validate(): void
    {
        Assert::required('CallbackUri', $this->callbackUri);
        Assert::required('AuthUri', $this->authUri);
        Assert::length('CallbackUri', $this->callbackUri, 1, 2048);
        Assert::length('AuthUri', $this->authUri, 1, 2048);
        Assert::httpsUrl('CallbackUri', $this->callbackUri);
        Assert::httpsUrl('AuthUri', $this->authUri);
    }

    /**
     * @return list<NotificationType>
     */
    public function types(): array
    {
        return array_values(array_filter(array_map(NotificationType::fromLoose(...), $this->filter ?? [])));
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $a = Attributes::of($data);

        return new self(
            callbackUri: $a->string('CallbackUri') ?? '',
            authUri: $a->string('AuthUri') ?? '',
            filter: $a->has('Filter') ? $a->strings('Filter') : null,
        );
    }

    protected function fields(): array
    {
        return [
            'AuthUri' => $this->authUri,
            'CallbackUri' => $this->callbackUri,
            'Filter' => $this->filter,
        ];
    }

    public function __debugInfo(): array
    {
        // AuthUri may embed client credentials in its query string.
        return ['callbackUri' => $this->callbackUri, 'authUri' => preg_replace('/\?.*$/', '?***', $this->authUri), 'filter' => $this->filter];
    }
}
