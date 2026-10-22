<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Context;

use Rasuvaeff\JsonLd\Iri;

/**
 * The active context produced by context processing: term definitions,
 * keyword aliases and the default base/vocab/language/direction members.
 *
 * Immutable; the processor builds a new instance per processed context.
 *
 * @api
 */
final readonly class ActiveContext
{
    /**
     * @param Iri|null $base document base for relative IRI resolution
     * @param string|null $vocab vocabulary IRI for term concatenation
     * @param string|null $language default language of the context
     * @param string|null $direction default text direction of the context
     * @param self|null $previousContext context this one was created from by a non-propagating context
     * @param array<string, TermDefinition|null> $terms term => definition; a null value marks a term explicitly mapped to nothing
     * @param array<string, string> $keywordAliases term => aliased keyword
     * @param array<string, string> $aliasesByKeyword keyword => alias term
     */
    public function __construct(
        public ?Iri $base = null,
        public ?string $vocab = null,
        public ?string $language = null,
        public ?string $direction = null,
        public ?self $previousContext = null,
        public array $terms = [],
        public array $keywordAliases = [],
        public array $aliasesByKeyword = [],
    ) {}

    /**
     * The definition of an exactly matching term, or null when the term is
     * unknown or explicitly mapped to nothing.
     */
    public function term(string $term): ?TermDefinition
    {
        return $this->terms[$term] ?? null;
    }

    public function isMapped(string $term): bool
    {
        return array_key_exists($term, $this->terms);
    }

    public function keywordFor(string $term): ?string
    {
        return $this->keywordAliases[$term] ?? null;
    }

    public function aliasFor(string $keyword): ?string
    {
        return $this->aliasesByKeyword[$keyword] ?? null;
    }
}
