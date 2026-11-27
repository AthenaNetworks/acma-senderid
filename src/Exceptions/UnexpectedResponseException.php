<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Exceptions;

/**
 * ACMA answered with a success status, but the response could not be used
 * (not JSON, or missing the ID of the record it created).
 *
 * The request may well have been applied, so this extends ServerException:
 * treat it like a 5xx and do not blindly repeat a create.
 */
class UnexpectedResponseException extends ServerException
{
}
