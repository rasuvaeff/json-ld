<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Loader;

/**
 * A context (or document) loaded by a {@see ContextLoaderInterface}.
 *
 * @api
 */
final readonly class LoadedContext
{
    /**
     * @param string $documentUrl final location of the document; empty when the reference was inline JSON
     * @param array<string|int, mixed> $document decoded document (object or array at the top level)
     * @param string|null $contextUrl location of an alternate context advertised by the source (HTTP Link header semantics), if any
     */
    public function __construct(
        public string $documentUrl,
        public array $document,
        public ?string $contextUrl = null,
    ) {}
}
