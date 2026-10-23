<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests\Support;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Signals that a reference passed the loader's scheme and policy checks:
 * it throws a distinctive exception instead of creating a request.
 */
final readonly class ReachedRequestFactory implements RequestFactoryInterface
{
    public const string MESSAGE = 'request factory reached';

    #[\Override]
    public function createRequest(string $method, $uri): RequestInterface
    {
        throw new \LogicException(self::MESSAGE);
    }
}
