<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Context;

/**
 * An immutable term definition, JSON-LD 1.1 API section 7.5.
 *
 * @api
 */
final readonly class TermDefinition
{
    /**
     * @param string|null $iriMapping absolute IRI the term expands to, null when the term maps to nothing
     * @param string|null $keyword aliased keyword, when the term maps to `@id`, `@type` and so on
     * @param bool $prefix the term may be used as a compact IRI prefix
     * @param bool $protected the definition survives scoped context resets
     * @param bool $reverse the term was defined through `@reverse`
     * @param list<string> $container container mapping as a unique list (`@list`, `@set`, …)
     * @param string|null $index index mapping, requires the `@index` container
     * @param string|null $language default language for values of this term, `@none` disables inheritance
     * @param string|null $direction default text direction for values of this term
     * @param string|null $type type coercion: `@id`, `@json`, `@vocab` or an absolute IRI
     * @param string|null $nest property carrying nested properties, `@nest` or a term
     * @param mixed $localContext scoped context attached to the term, still in raw form
     */
    public function __construct(
        public ?string $iriMapping,
        public ?string $keyword = null,
        public bool $prefix = false,
        public bool $protected = false,
        public bool $reverse = false,
        public array $container = [],
        public ?string $index = null,
        public ?string $language = null,
        public ?string $direction = null,
        public ?string $type = null,
        public ?string $nest = null,
        public mixed $localContext = null,
        /**
         * Where the scoped context came from: its relative references
         * resolve against this location, not against `@base`.
         */
        public ?string $contextBaseUrl = null,
    ) {}

    public function mapsNothing(): bool
    {
        return $this->iriMapping === null && $this->keyword === null;
    }
}
