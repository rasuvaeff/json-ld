<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests\Support;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * Plays both the outgoing request and the incoming response of a stubbed exchange.
 */
final class StubMessage implements RequestInterface, ResponseInterface
{
    /** @var array<string, string> */
    public array $headers = [];

    public function __construct(
        private readonly int $status = 200,
        private readonly StreamInterface $body = new StringStream(''),
    ) {}

    #[\Override]
    public function withHeader($name, $value): static
    {
        $copy = clone $this;
        $copy->headers[(string) $name] = (string) (is_array($value) ? implode(', ', $value) : $value);

        return $copy;
    }

    #[\Override]
    public function getStatusCode(): int
    {
        return $this->status;
    }

    #[\Override]
    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    #[\Override]
    public function withStatus($code, $reasonPhrase = ''): static
    {
        throw new \LogicException('unused');
    }

    #[\Override]
    public function getReasonPhrase(): string
    {
        return '';
    }

    #[\Override]
    public function getProtocolVersion(): string
    {
        return '1.1';
    }

    #[\Override]
    public function withProtocolVersion($version): static
    {
        throw new \LogicException('unused');
    }

    #[\Override]
    public function getHeaders(): array
    {
        return [];
    }

    #[\Override]
    public function hasHeader($name): bool
    {
        return false;
    }

    #[\Override]
    public function getHeader($name): array
    {
        return [];
    }

    #[\Override]
    public function getHeaderLine($name): string
    {
        return '';
    }

    #[\Override]
    public function withAddedHeader($name, $value): static
    {
        throw new \LogicException('unused');
    }

    #[\Override]
    public function withoutHeader($name): static
    {
        throw new \LogicException('unused');
    }

    #[\Override]
    public function withBody(StreamInterface $body): static
    {
        throw new \LogicException('unused');
    }

    #[\Override]
    public function getRequestTarget(): string
    {
        return '/';
    }

    #[\Override]
    public function withRequestTarget($requestTarget): static
    {
        throw new \LogicException('unused');
    }

    #[\Override]
    public function getMethod(): string
    {
        return 'GET';
    }

    #[\Override]
    public function withMethod($method): static
    {
        throw new \LogicException('unused');
    }

    #[\Override]
    public function getUri(): UriInterface
    {
        throw new \LogicException('unused');
    }

    #[\Override]
    public function withUri(UriInterface $uri, $preserveHost = false): static
    {
        throw new \LogicException('unused');
    }
}
