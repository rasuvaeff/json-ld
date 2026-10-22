<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Rdf;

/**
 * An RDF blank node.
 *
 * @api
 */
final readonly class BlankNode
{
    public function __construct(public string $id) {}

    public function __toString(): string
    {
        return '_:' . $this->id;
    }
}
