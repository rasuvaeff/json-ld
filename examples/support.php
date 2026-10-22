<?php

declare(strict_types=1);

use Rasuvaeff\JsonLd\Exception\DocumentLoadingException;
use Rasuvaeff\JsonLd\Loader\ContextLoaderInterface;
use Rasuvaeff\JsonLd\Loader\LoadedContext;

/**
 * A loader that serves contexts from memory, so the examples run offline.
 */
final class MemoryLoader implements ContextLoaderInterface
{
    /** @param array<string, array<string, mixed>> $documents */
    public function __construct(private array $documents) {}

    #[\Override]
    public function load(string $reference): LoadedContext
    {
        if (!isset($this->documents[$reference])) {
            throw new DocumentLoadingException(sprintf('"%s" is not available', $reference));
        }

        return new LoadedContext(documentUrl: $reference, document: $this->documents[$reference]);
    }
}

return new MemoryLoader([
    'https://schema.org/' => ['@context' => ['@vocab' => 'https://schema.org/']],
]);
