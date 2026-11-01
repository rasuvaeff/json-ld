<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Rdf;

use InvalidArgumentException;
use Rasuvaeff\JsonLd\Exception\CanonicalizationLimitException;

/**
 * W3C RDF Dataset Canonicalization (RDFC-1.0): relabels blank nodes so that
 * two datasets have the same canonical N-Quads exactly when they are
 * isomorphic.
 *
 * Some datasets ("poison graphs", RDFC-1.0 section 4.4.1) need factorial
 * work. With `n` blank nodes that share their first-degree hash with
 * another one, Hash N-Degree Quads may run at most `$workFactor × n` times
 * for each blank node, which bounds the whole run to `$workFactor × n²`
 * calls; past that the canonicalizer gives up with a
 * {@see CanonicalizationLimitException}. Symmetric but legitimate shapes
 * (a cycle, a list of equal values) need about `n` calls per blank node.
 *
 * @api
 */
final readonly class Canonicalizer
{
    public const int DEFAULT_WORK_FACTOR = 4;

    /**
     * @param HashAlgorithm $hashAlgorithm the hash function of the algorithm and of {@see CanonicalForm::hash()}
     * @param int $workFactor multiple of the number of non-unique blank nodes that one blank node may spend
     *                        in Hash N-Degree Quads
     *
     * @throws InvalidArgumentException when the limit is not positive
     */
    public function __construct(
        private HashAlgorithm $hashAlgorithm = HashAlgorithm::Sha256,
        private int $workFactor = self::DEFAULT_WORK_FACTOR,
    ) {
        if ($workFactor < 1) {
            throw new InvalidArgumentException(sprintf('Work factor must be positive, %d given', $workFactor));
        }
    }

    /**
     * @throws CanonicalizationLimitException when the dataset needs more work than the limit allows
     */
    public function canonicalize(Dataset $dataset): CanonicalForm
    {
        return (new CanonicalizationRun($this->hashAlgorithm, $this->workFactor))->canonicalize($dataset);
    }
}
