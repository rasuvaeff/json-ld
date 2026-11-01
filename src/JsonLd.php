<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd;

use Rasuvaeff\JsonLd\Graph\Document;
use Rasuvaeff\JsonLd\Rdf\Dataset;
use Rasuvaeff\JsonLd\Rdf\FromRdf;
use Rasuvaeff\JsonLd\Rdf\NQuads;
use Rasuvaeff\JsonLd\Rdf\ToRdf;

/**
 * The JSON-LD 1.1 processor facade: expansion, flattening, the node API
 * and RDF conversion.
 *
 * Every operation performs I/O only through the loader given in
 * {@see ProcessingOptions}; the default loader parses inline JSON and never
 * touches the network or the filesystem. Failures are
 * {@see Exception\JsonLdException}s; {@see Exception\JsonLdException::errorCode()}
 * tells them apart.
 *
 * @api
 */
final class JsonLd
{
    /**
     * Expands a JSON-LD document.
     *
     * @param string|array<mixed> $input decoded document or inline JSON string
     *
     * @return list<mixed> the expanded form
     */
    public static function expand(string|array $input, ?ProcessingOptions $options = null): array
    {
        return (new Expander())->expand($input, $options);
    }

    /**
     * Flattens a JSON-LD document: every node of the default graph, embedded
     * nodes replaced by references; named graphs are `@graph` members of
     * their graph node.
     *
     * @param string|array<mixed> $input
     *
     * @return list<array<string, mixed>>
     */
    public static function flatten(string|array $input, ?ProcessingOptions $options = null): array
    {
        return (new Flattener())->flatten($input, $options);
    }

    /**
     * Exposes a document through the node API. Embedded nodes are nodes of
     * their graph; the ones without `@id` get blank node labels
     * (see {@see Document}).
     *
     * @param string|array<mixed> $input
     */
    public static function document(string|array $input, ?ProcessingOptions $options = null): Document
    {
        return Document::fromExpanded(DocumentExpansion::run($input, $options));
    }

    /**
     * Converts a JSON-LD document into an RDF dataset.
     *
     * @param string|array<mixed> $input
     */
    public static function toRdf(
        string|array $input,
        ?ProcessingOptions $options = null,
        bool $produceGeneralizedRdf = false,
        ?RdfDirection $rdfDirection = null,
    ): Dataset {
        return (new ToRdf(produceGeneralizedRdf: $produceGeneralizedRdf, rdfDirection: $rdfDirection))->toRdf($input, $options);
    }

    /**
     * Converts an RDF dataset into an expanded JSON-LD document.
     *
     * @return list<mixed>
     */
    public static function fromRdf(
        Dataset $dataset,
        bool $useNativeTypes = false,
        bool $useRdfType = false,
        ?RdfDirection $rdfDirection = null,
    ): array {
        return (new FromRdf(useNativeTypes: $useNativeTypes, useRdfType: $useRdfType, rdfDirection: $rdfDirection))->fromRdf($dataset);
    }

    public static function serializeNQuads(Dataset $dataset): string
    {
        return (new NQuads())->serialize($dataset);
    }

    public static function parseNQuads(string $nquads): Dataset
    {
        return (new NQuads())->parse($nquads);
    }
}
