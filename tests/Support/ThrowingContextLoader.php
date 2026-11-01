<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests\Support;

use Rasuvaeff\JsonLd\Loader\ContextLoaderInterface;
use Rasuvaeff\JsonLd\Loader\LoadedContext;

/**
 * A loader that fails with the given throwable, whatever is asked.
 */
final readonly class ThrowingContextLoader implements ContextLoaderInterface
{
    public function __construct(private \Throwable $failure) {}

    #[\Override]
    public function load(string $reference): LoadedContext
    {
        throw $this->failure;
    }
}
