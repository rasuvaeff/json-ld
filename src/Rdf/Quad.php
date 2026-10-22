<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Rdf;

/**
 * An RDF statement. The graph name is null for the default graph.
 *
 * @api
 */
final readonly class Quad
{
    public function __construct(
        public Iri|BlankNode $subject,
        public Iri $predicate,
        public Iri|BlankNode|Literal $object,
        public Iri|BlankNode|null $graph = null,
    ) {}
}
