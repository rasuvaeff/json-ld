<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Context;

/**
 * State shared by the term definitions created while one local context is
 * processed: the context itself, the "defined" map used for cycle detection
 * and the options the enclosing run was started with.
 *
 * @internal
 */
final class DefinitionScope
{
    /**
     * @param array<string, mixed> $local the local context being processed
     * @param list<string> $remote remote contexts currently being loaded
     * @param array<string, bool> $defined term => definition finished
     */
    public function __construct(
        public readonly array $local,
        public readonly ?string $baseUrl,
        public readonly bool $protected,
        public readonly bool $overrideProtected,
        public readonly array $remote,
        public readonly bool $validateScoped,
        public array $defined = [],
    ) {}
}
