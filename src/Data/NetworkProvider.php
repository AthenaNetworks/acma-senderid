<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Data;

use AthenaNetworks\AcmaSenderId\Support\Attributes;

/**
 * A Mobile Network Operator. ACMA does not use these: the API always returns a
 * single placeholder whose ID must be used when creating chatbots.
 */
final readonly class NetworkProvider
{
    public const string DEFAULT_ID = '00000000-0000-0000-0000-000000000000';

    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public string $id,
        public ?string $name,
        public ?string $verified,
        public ?string $status,
        public array $raw = [],
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $a = Attributes::of($data);

        return new self(
            id: $a->string('NetworkProviderId') ?? self::DEFAULT_ID,
            name: $a->string('NetworkProviderName'),
            verified: $a->string('NetworkProviderVerified'),
            status: $a->string('NetworkProviderStatus'),
            raw: $data,
        );
    }
}
