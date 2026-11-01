<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Rdf;

use Rasuvaeff\JsonLd\Exception\CanonicalizationLimitException;

/**
 * An immutable RDF dataset.
 *
 * @api
 */
final readonly class Dataset
{
    /** @param list<Quad> $quads */
    public function __construct(public array $quads) {}

    public static function of(Quad ...$quads): self
    {
        return new self(array_values($quads));
    }

    /**
     * Canonical N-Quads of the dataset (RDFC-1.0 with SHA-256 and the
     * default work limit); {@see Canonicalizer} takes other settings.
     *
     * @throws CanonicalizationLimitException
     */
    public function canonicalNQuads(): string
    {
        return (new Canonicalizer())->canonicalize($this)->nquads;
    }

    /**
     * SHA-256 hex digest of {@see self::canonicalNQuads()}: the same for every
     * isomorphic dataset, whatever the order of the quads and the blank node
     * labels.
     *
     * @throws CanonicalizationLimitException
     */
    public function canonicalHash(): string
    {
        return (new Canonicalizer())->canonicalize($this)->hash();
    }

    /**
     * Two datasets are equal when they are isomorphic: the same quads up to
     * order, duplicates and blank node labels. Compares canonical N-Quads.
     *
     * @throws CanonicalizationLimitException
     */
    public function equals(self $other): bool
    {
        return $this->canonicalNQuads() === $other->canonicalNQuads();
    }
}
