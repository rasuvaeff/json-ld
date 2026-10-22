<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Context;

/**
 * An immutable term definition, JSON-LD 1.1 API section 4.2.
 *
 * @api
 */
final readonly class TermDefinition
{
    /**
     * @param string|null $iri IRI mapping: an absolute IRI, a blank node identifier or a keyword; null maps the term to nothing
     * @param bool $prefix the term may be used as a compact IRI prefix
     * @param bool $protected the definition survives context overrides
     * @param bool $reverse the term is a reverse property
     * @param list<string> $container container mapping, sorted keywords
     * @param string|null $index property-valued index mapping
     * @param bool $hasLanguage a language mapping is present (possibly null)
     * @param string|null $language language mapping
     * @param bool $hasDirection a direction mapping is present (possibly null)
     * @param string|null $direction direction mapping
     * @param string|null $type type mapping: `@id`, `@json`, `@none`, `@vocab` or an absolute IRI
     * @param string|null $nest nest value: `@nest` or a term
     * @param bool $hasContext a scoped context is attached
     * @param mixed $context scoped context in raw form
     * @param string|null $baseUrl location the scoped context was defined at
     */
    public function __construct(
        public ?string $iri,
        public bool $prefix = false,
        public bool $protected = false,
        public bool $reverse = false,
        public array $container = [],
        public ?string $index = null,
        public bool $hasLanguage = false,
        public ?string $language = null,
        public bool $hasDirection = false,
        public ?string $direction = null,
        public ?string $type = null,
        public ?string $nest = null,
        public bool $hasContext = false,
        public mixed $context = null,
        public ?string $baseUrl = null,
    ) {}

    public function withProtected(bool $protected): self
    {
        return new self(
            $this->iri,
            $this->prefix,
            $protected,
            $this->reverse,
            $this->container,
            $this->index,
            $this->hasLanguage,
            $this->language,
            $this->hasDirection,
            $this->direction,
            $this->type,
            $this->nest,
            $this->hasContext,
            $this->context,
            $this->baseUrl,
        );
    }

    public function sameAs(self $other): bool
    {
        return $this->withProtected(protected: false) == $other->withProtected(protected: false);
    }
}
