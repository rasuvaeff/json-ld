<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests\Support;

use Rasuvaeff\JsonLd\Loader\ContextLoaderInterface;
use Rasuvaeff\JsonLd\Loader\LoadedContext;

/**
 * Records the references the processor asks for and serves an empty context.
 */
final class RecordingContextLoader implements ContextLoaderInterface
{
    /** @var list<string> */
    public array $requested = [];

    #[\Override]
    public function load(string $reference): LoadedContext
    {
        $this->requested[] = $reference;

        return new LoadedContext(documentUrl: $reference, document: ['@context' => []]);
    }
}
