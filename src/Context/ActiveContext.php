<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Context;

/**
 * The active context produced by context processing: term definitions and
 * the default base/vocab/language/direction members.
 *
 * Instances are copied (`clone`) before the processor changes them; they
 * must not be mutated once handed to the expansion.
 *
 * @internal
 */
final class ActiveContext
{
    /**
     * @param string|null $base base IRI for relative IRI resolution
     * @param string|null $originalBase base IRI the processing run started with
     * @param string|null $vocab vocabulary mapping
     * @param string|null $language default language
     * @param string|null $direction default base direction
     * @param self|null $previous context that applies again outside of a non-propagated scope
     * @param array<string, TermDefinition> $terms term => definition
     */
    public function __construct(
        public ?string $base = null,
        public ?string $originalBase = null,
        public ?string $vocab = null,
        public ?string $language = null,
        public ?string $direction = null,
        public ?self $previous = null,
        public array $terms = [],
    ) {}

    public function term(string $term): ?TermDefinition
    {
        return $this->terms[$term] ?? null;
    }

    public function hasProtectedTerms(): bool
    {
        foreach ($this->terms as $definition) {
            if ($definition->protected) {
                return true;
            }
        }

        return false;
    }
}
