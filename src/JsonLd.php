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
 * {@see ExpandOptions}; the default loader parses inline JSON and never
 * touches the network or the filesystem.
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
    public static function expand(string|array $input, ?ExpandOptions $options = null): array
    {
        return (new Expander())->expand($input, $options);
    }

    /**
     * Flattens a JSON-LD document into a single node object with `@graph`.
     *
     * @param string|array<mixed> $input
     *
     * @return array<string, mixed>
     */
    public static function flatten(string|array $input, ?ExpandOptions $options = null): array
    {
        return (new Flattener())->flatten($input, $options);
    }

    /**
     * Expands a document and exposes it through the node API.
     *
     * @param string|array<mixed> $input
     */
    public static function document(string|array $input, ?ExpandOptions $options = null): Document
    {
        return Document::fromExpanded(self::expand($input, $options));
    }

    /**
     * Converts a JSON-LD document into an RDF dataset.
     *
     * @param string|array<mixed> $input
     */
    public static function toRdf(
        string|array $input,
        ?ExpandOptions $options = null,
        bool $produceGeneralizedRdf = false,
        ?string $rdfDirection = null,
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
        ?string $rdfDirection = null,
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
