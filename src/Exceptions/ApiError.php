<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Exceptions;

/**
 * A single error entry from an RcsRequestErrorResponse body.
 */
final readonly class ApiError
{
    public function __construct(
        public ?string $code,
        public ?string $message,
    ) {
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['code']) ? (string) $data['code'] : null,
            isset($data['message']) ? (string) $data['message'] : null,
        );
    }
}
