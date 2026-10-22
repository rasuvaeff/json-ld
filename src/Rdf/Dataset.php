<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Rdf;

/**
 * An immutable RDF dataset.
 *
 * @api
 */
final readonly class Dataset
{
    /** @param list<Quad> $quads */
    public function __construct(public array $quads) {}

    /**
     * @param list<Quad> $quads
     */
    public static function of(Quad ...$quads): self
    {
        return new self(array_values($quads));
    }

    /** @return list<Quad> */
    public function quads(): array
    {
        return $this->quads;
    }

    /**
     * Two datasets are equal when they hold the same quads, regardless of
     * order and of blank node labels (isomorphism).
     */
    public function equals(self $other): bool
    {
        return Canonicalizer::canonicalize($this) === Canonicalizer::canonicalize($other);
    }

    /**
     * Order- and blank-label-independent hash of the dataset.
     */
    public function canonicalHash(): string
    {
        return hash('sha256', Canonicalizer::canonicalize($this));
    }
}
