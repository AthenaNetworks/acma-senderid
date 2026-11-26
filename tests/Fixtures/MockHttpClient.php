<?php

declare(strict_types=1);

namespace AthenaNetworks\AcmaSenderId\Tests\Fixtures;

use Closure;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * A PSR-18 client that returns queued responses and records requests.
 *
 * Token endpoint requests are answered automatically unless a token response is queued.
 */
final class MockHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<ResponseInterface|Throwable|Closure(RequestInterface): ResponseInterface> */
    private array $queue = [];

    /** @var list<ResponseInterface|Throwable> */
    private array $tokenQueue = [];

    public int $tokensIssued = 0;

    public function queue(ResponseInterface|Throwable|Closure ...$responses): self
    {
        array_push($this->queue, ...$responses);

        return $this;
    }

    /**
     * @param array<array-key, mixed>|null $body
     */
    public function queueJson(?array $body, int $status = 200): self
    {
        return $this->queue(self::json($body, $status));
    }

    public function queueToken(ResponseInterface|Throwable $response): self
    {
        $this->tokenQueue[] = $response;

        return $this;
    }

    /**
     * @param array<array-key, mixed>|null $body
     */
    public static function json(?array $body, int $status = 200): ResponseInterface
    {
        return new Response($status, ['Content-Type' => 'application/json'], $body === null ? '' : (string) json_encode($body));
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if (str_contains((string) $request->getUri(), '/oauth2/v2.0/token')) {
            $this->tokenRequests[] = $request;
            $next = array_shift($this->tokenQueue);
            if ($next instanceof Throwable) {
                throw $next;
            }
            if ($next instanceof ResponseInterface) {
                return $next;
            }
            $this->tokensIssued++;

            return self::json(['access_token' => 'token-' . $this->tokensIssued, 'token_type' => 'Bearer', 'expires_in' => 3600]);
        }

        $this->requests[] = $request;
        $next = array_shift($this->queue);

        if ($next === null) {
            throw new RuntimeException('No response queued for ' . $request->getMethod() . ' ' . $request->getUri());
        }
        if ($next instanceof Throwable) {
            throw $next;
        }
        if ($next instanceof Closure) {
            return $next($request);
        }

        return $next;
    }

    /** @var list<RequestInterface> */
    public array $tokenRequests = [];

    public function lastRequest(): RequestInterface
    {
        return $this->requests[array_key_last($this->requests)] ?? throw new RuntimeException('No requests sent');
    }

    /**
     * @return array<array-key, mixed>
     */
    public function lastJson(): array
    {
        $request = $this->lastRequest();
        $request->getBody()->rewind();

        return (array) json_decode((string) $request->getBody(), true);
    }
}
