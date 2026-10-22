<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Rdf;

/**
 * An RDF IRI reference.
 *
 * @api
 */
final readonly class Iri
{
    public function __construct(public string $value) {}

    public function __toString(): string
    {
        return $this->value;
    }
}
