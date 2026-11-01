<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Exception;

/**
 * RDF Dataset Canonicalization gave up: the dataset needs more work than
 * the configured limit allows (RDFC-1.0 section 4.4.1, "poison graphs").
 *
 * @api
 */
final class CanonicalizationLimitException extends JsonLdException
{
    public function __construct(int $maxDeepIterations)
    {
        parent::__construct(
            ErrorCode::CanonicalizationLimitExceeded,
            sprintf('canonicalization limit exceeded: Hash N-Degree Quads ran more than %d times for one blank node', $maxDeepIterations),
        );
    }
}
