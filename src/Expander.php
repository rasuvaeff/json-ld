<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd;

use Rasuvaeff\JsonLd\Exception\JsonLdException;

/**
 * Expansion, JSON-LD 1.1 API section 5.1: turns compact documents into the
 * expanded form.
 *
 * @api
 */
final readonly class Expander
{
    /**
     * Expands a document given as decoded JSON or as an inline JSON string.
     *
     * @param string|array<mixed> $input
     *
     * @return list<mixed> the expanded form, always a list of node/value objects
     *
     * @throws JsonLdException {@see JsonLdException::errorCode()} tells the W3C error code
     */
    public function expand(string|array $input, ?ProcessingOptions $options = null): array
    {
        /** @var list<mixed> */
        return ExpansionRun::strip(DocumentExpansion::run($input, $options));
    }
}
