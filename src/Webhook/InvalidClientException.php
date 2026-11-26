<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Webhook;

use AthenaNetworks\AcmaSenderId\Exceptions\SenderIdException;
use RuntimeException;

/**
 * The credentials presented to the webhook token endpoint were wrong.
 */
final class InvalidClientException extends RuntimeException implements SenderIdException
{
}
