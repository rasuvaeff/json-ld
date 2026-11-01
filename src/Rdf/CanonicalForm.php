<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Rdf;

/**
 * The result of RDF Dataset Canonicalization: the canonical N-Quads
 * document and the canonical labels issued for the blank nodes of the
 * input.
 *
 * @api
 */
final readonly class CanonicalForm
{
    /**
     * @param string $nquads canonical N-Quads: one statement per line, each ending with "\n", sorted by code point
     * @param array<string, string> $issuedIdentifiers input blank node label => canonical label, both with `_:`
     *                                                 (`_:e0` => `_:c14n0`), in issue order
     * @param HashAlgorithm $hashAlgorithm the algorithm the canonicalization used
     */
    public function __construct(
        public string $nquads,
        public array $issuedIdentifiers,
        public HashAlgorithm $hashAlgorithm,
    ) {}

    /**
     * Hex digest of {@see self::$nquads} with the canonicalization's algorithm.
     */
    public function hash(): string
    {
        return $this->hashAlgorithm->hash($this->nquads);
    }
}
