<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Rdf;

/**
 * Hash algorithm of RDF Dataset Canonicalization (RDFC-1.0 section 4.2):
 * SHA-256 by default, SHA-384 as the specification's alternative. The value
 * is the algorithm name of PHP's `hash()`.
 *
 * @api
 */
enum HashAlgorithm: string
{
    case Sha256 = 'sha256';

    case Sha384 = 'sha384';

    public function hash(string $data): string
    {
        return hash($this->value, $data);
    }
}
