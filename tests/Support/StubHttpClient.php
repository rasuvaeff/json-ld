<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests\Support;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A client and request factory in one: answers every request with one canned response
 * (or throws a client exception) and remembers what it was asked.
 */
final class StubHttpClient implements ClientInterface, RequestFactoryInterface
{
    public ?RequestInterface $lastRequest = null;

    public ?string $requestedUrl = null;

    public int $sent = 0;

    public function __construct(
        private readonly ?ResponseInterface $response = null,
        private readonly ?string $failure = null,
    ) {}

    #[\Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        ++$this->sent;
        $this->lastRequest = $request;

        if ($this->failure !== null) {
            throw new StubClientException($this->failure);
        }

        return $this->response ?? new StubMessage();
    }

    #[\Override]
    public function createRequest(string $method, $uri): RequestInterface
    {
        $this->requestedUrl = (string) $uri;

        return new StubMessage();
    }
}
