<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Exceptions;

use RuntimeException;

/**
 * Thrown when the HTTP request could not be completed (DNS, TLS, timeout, ...).
 */
class TransportException extends RuntimeException implements SenderIdException
{
}
