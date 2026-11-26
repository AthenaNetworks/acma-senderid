<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Exceptions;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Thrown when the ACMA API responds with a non-successful status code.
 *
 * Subclasses exist for the common cases; catch this class to handle all of them.
 */
class ApiException extends RuntimeException implements SenderIdException
{
    /**
     * @param list<ApiError> $errors
     */
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly array $errors = [],
        public readonly ?string $status = null,
        public readonly ?RequestInterface $request = null,
        public readonly ?ResponseInterface $response = null,
    ) {
        parent::__construct($message, $statusCode);
    }

    public static function fromResponse(RequestInterface $request, ResponseInterface $response): self
    {
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();
        $decoded = $body === '' ? null : json_decode($body, true);

        $errors = [];
        $status = null;

        if (is_array($decoded)) {
            $status = isset($decoded['status']) ? (string) $decoded['status'] : null;
            foreach (is_array($decoded['messages'] ?? null) ? $decoded['messages'] : [] as $error) {
                if (is_array($error)) {
                    $errors[] = ApiError::fromArray($error);
                }
            }
        }

        $summary = $errors === []
            ? trim($statusCode . ' ' . $response->getReasonPhrase())
            : implode('; ', array_filter(array_map(
                static fn (ApiError $e): string => trim(($e->code !== null ? "[{$e->code}] " : '') . ($e->message ?? '')),
                $errors,
            )));

        $message = sprintf(
            'ACMA Sender ID API request %s %s failed: %s',
            $request->getMethod(),
            $request->getUri()->getPath(),
            $summary,
        );

        $class = match (true) {
            $statusCode === 400, $statusCode === 422 => ValidationException::class,
            $statusCode === 401 => AuthenticationException::class,
            $statusCode === 403 => AuthorizationException::class,
            $statusCode === 404 => NotFoundException::class,
            $statusCode === 429 => RateLimitException::class,
            $statusCode >= 500 => ServerException::class,
            default => self::class,
        };

        return new $class($message, $statusCode, $errors, $status, $request, $response);
    }

    /**
     * Error messages returned by the API.
     *
     * @return list<string>
     */
    public function messages(): array
    {
        return array_values(array_filter(array_map(static fn (ApiError $e): ?string => $e->message, $this->errors)));
    }
}
