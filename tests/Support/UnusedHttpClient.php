<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests\Support;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * An HTTP client that must never be reached.
 */
final readonly class UnusedHttpClient implements ClientInterface
{
    #[\Override]
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        throw new \LogicException('the client must not be called');
    }
}
