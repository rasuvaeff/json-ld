<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Context;

use Rasuvaeff\JsonLd\Iri;

/**
 * Mutable working copy of an {@see ActiveContext} used while a single local
 * context is being processed. Term definitions are created lazily and may
 * reference each other, so the builder lets the processing algorithm see a
 * partially built context. It freezes into an immutable snapshot once done.
 *
 * A `null` setter value resets the member, exactly like the JSON-LD
 * `@base`/`@vocab`/`@language`/`@direction` null semantics require.
 *
 * @internal
 */
final class ContextBuilder
{
    private ?Iri $base;

    private ?string $vocab;

    private ?string $language;

    private ?string $direction;

    /** @var array<string, TermDefinition|null> */
    private array $terms;

    /** @var array<string, string> */
    private array $keywordAliases;

    /** @var array<string, string> */
    private array $aliasesByKeyword;

    public function __construct(ActiveContext $initial)
    {
        $this->base = $initial->base;
        $this->vocab = $initial->vocab;
        $this->language = $initial->language;
        $this->direction = $initial->direction;
        $this->terms = $initial->terms;
        $this->keywordAliases = $initial->keywordAliases;
        $this->aliasesByKeyword = $initial->aliasesByKeyword;
    }

    public static function from(ActiveContext $context): self
    {
        return new self($context);
    }

    public function freeze(?ActiveContext $previousContext = null): ActiveContext
    {
        return new ActiveContext(
            base: $this->base,
            vocab: $this->vocab,
            language: $this->language,
            direction: $this->direction,
            previousContext: $previousContext,
            terms: $this->terms,
            keywordAliases: $this->keywordAliases,
            aliasesByKeyword: $this->aliasesByKeyword,
        );
    }

    public function base(): ?Iri
    {
        return $this->base;
    }

    public function setBase(?Iri $base): void
    {
        $this->base = $base;
    }

    public function vocab(): ?string
    {
        return $this->vocab;
    }

    public function setVocab(?string $vocab): void
    {
        $this->vocab = $vocab;
    }

    public function language(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): void
    {
        $this->language = $language;
    }

    public function direction(): ?string
    {
        return $this->direction;
    }

    public function setDirection(?string $direction): void
    {
        $this->direction = $direction;
    }

    public function term(string $term): ?TermDefinition
    {
        return $this->terms[$term] ?? null;
    }

    public function isMapped(string $term): bool
    {
        return array_key_exists($term, $this->terms);
    }

    public function setTerm(string $term, ?TermDefinition $definition): void
    {
        $this->removeTerm($term);
        $this->terms[$term] = $definition;
    }

    public function removeTerm(string $term): void
    {
        unset($this->terms[$term]);

        $keyword = $this->keywordAliases[$term] ?? null;

        if ($keyword !== null) {
            unset($this->keywordAliases[$term], $this->aliasesByKeyword[$keyword]);
        }
    }

    /**
     * Registers the term as the alias of a keyword; a keyword may have at
     * most one alias per active context.
     */
    public function alias(string $term, string $keyword): void
    {
        $this->keywordAliases[$term] = $keyword;
        $this->aliasesByKeyword[$keyword] = $term;
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
