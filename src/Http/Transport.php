<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Http;

use AthenaNetworks\AcmaSenderId\Auth\TokenProvider;
use AthenaNetworks\AcmaSenderId\Config;
use AthenaNetworks\AcmaSenderId\Exceptions\ApiException;
use AthenaNetworks\AcmaSenderId\Exceptions\TransportException;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Sends authenticated JSON requests to the ACMA API and maps failures to exceptions.
 *
 * Low-level: prefer the resource classes on {@see \AthenaNetworks\AcmaSenderId\SenderIdClient}.
 * Exposed via SenderIdClient::transport() for endpoints this package does not wrap.
 */
final class Transport
{
    public function __construct(
        private readonly Config $config,
        private readonly TokenProvider $tokens,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    /**
     * Build a versioned API path, e.g. path('brands', $id) => "rcsva/v1/brands/{id}".
     */
    public function path(string ...$segments): string
    {
        return 'rcsva/' . $this->config->version . '/' . implode('/', array_map(rawurlencode(...), $segments));
    }

    /**
     * @param array<string, scalar|list<scalar>|null> $query
     * @return array<array-key, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query);
    }

    /**
     * @param array<array-key, mixed>|null $body
     * @param array<string, scalar|list<scalar>|null> $query
     * @return array<array-key, mixed>
     */
    public function post(string $path, ?array $body = null, array $query = []): array
    {
        return $this->request('POST', $path, $query, $body);
    }

    /**
     * @param array<array-key, mixed> $body
     * @return array<array-key, mixed>
     */
    public function patch(string $path, array $body): array
    {
        return $this->request('PATCH', $path, [], $body);
    }

    /**
     * @param array<array-key, mixed> $body
     * @return array<array-key, mixed>
     */
    public function put(string $path, array $body): array
    {
        return $this->request('PUT', $path, [], $body);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function delete(string $path): array
    {
        return $this->request('DELETE', $path);
    }

    /**
     * @param array<string, scalar|list<scalar>|null> $query
     * @param array<array-key, mixed>|null $body
     * @return array<array-key, mixed>
     */
    public function request(string $method, string $path, array $query = [], ?array $body = null): array
    {
        $request = $this->buildRequest($method, $path, $query, $body);
        $response = $this->send($request);

        // A cached token may have been revoked or rotated: refresh once and retry.
        if ($response->getStatusCode() === 401) {
            $this->tokens->invalidate();
            $request = $request->withHeader('Authorization', $this->authorization());
            $response = $this->send($request);
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw ApiException::fromResponse($request, $response);
        }

        return $this->decode($request, $response);
    }

    /**
     * @param array<string, scalar|list<scalar>|null> $query
     * @param array<array-key, mixed>|null $body
     */
    private function buildRequest(string $method, string $path, array $query, ?array $body): RequestInterface
    {
        $uri = $this->config->baseUri . ltrim($path, '/');
        $queryString = self::buildQuery($query);
        if ($queryString !== '') {
            $uri .= '?' . $queryString;
        }

        $request = $this->requestFactory->createRequest($method, $uri)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->config->userAgent)
            ->withHeader('Authorization', $this->authorization());

        if ($body !== null) {
            try {
                $json = json_encode((object) $body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } catch (JsonException $e) {
                throw new TransportException('Unable to encode request body as JSON: ' . $e->getMessage(), 0, $e);
            }

            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($json));
        }

        return $request;
    }

    private function authorization(): string
    {
        $token = $this->tokens->getToken();

        return 'Bearer ' . $token->token;
    }

    private function send(RequestInterface $request): ResponseInterface
    {
        try {
            return $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new TransportException(
                sprintf('Request %s %s failed: %s', $request->getMethod(), $request->getUri()->getPath(), $e->getMessage()),
                0,
                $e,
            );
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decode(RequestInterface $request, ResponseInterface $response): array
    {
        $body = trim((string) $response->getBody());
        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            throw new ApiException(
                sprintf('ACMA Sender ID API returned a non-JSON response for %s %s', $request->getMethod(), $request->getUri()->getPath()),
                $response->getStatusCode(),
                request: $request,
                response: $response,
            );
        }

        return $decoded;
    }

    /**
     * Build a query string, repeating keys for list values (verified=a&verified=b),
     * which is the format ASP.NET model binding expects for arrays.
     *
     * @param array<string, scalar|list<scalar>|null> $query
     */
    public static function buildQuery(array $query): string
    {
        $parts = [];
        foreach ($query as $key => $value) {
            if ($value === null) {
                continue;
            }
            foreach (is_array($value) ? $value : [$value] as $item) {
                $item = is_bool($item) ? ($item ? 'true' : 'false') : (string) $item;
                $parts[] = rawurlencode($key) . '=' . rawurlencode($item);
            }
        }

        return implode('&', $parts);
    }
}
