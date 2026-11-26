<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Exceptions;

/**
 * Thrown before a request is sent when input fails client-side validation
 * (required fields, maximum lengths, formats) derived from the OpenAPI spec.
 */
class InvalidArgumentException extends \InvalidArgumentException implements SenderIdException
{
    public function __construct(string $message, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }

    public static function forField(string $field, string $message): self
    {
        return new self("{$field}: {$message}", $field);
    }
}
