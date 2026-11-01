<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Context\ActiveContext;
use Rasuvaeff\JsonLd\Context\ContextProcessor;
use Rasuvaeff\JsonLd\Context\DefinitionScope;
use Rasuvaeff\JsonLd\Context\Syntax;
use Rasuvaeff\JsonLd\Context\TermDefinition;
use Rasuvaeff\JsonLd\DocumentExpansion;
use Rasuvaeff\JsonLd\Exception\JsonLdException;
use Rasuvaeff\JsonLd\Expander;
use Rasuvaeff\JsonLd\ExpansionRun;
use Rasuvaeff\JsonLd\Flattener;
use Rasuvaeff\JsonLd\Graph\Document;
use Rasuvaeff\JsonLd\Graph\Graph;
use Rasuvaeff\JsonLd\Graph\Node;
use Rasuvaeff\JsonLd\Graph\Value;
use Rasuvaeff\JsonLd\Iri;
use Rasuvaeff\JsonLd\Json;
use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\Keywords;
use Rasuvaeff\JsonLd\Loader\InlineContextLoader;
use Rasuvaeff\JsonLd\Loader\LoadedContext;
use Rasuvaeff\JsonLd\Loader\MapContextLoader;
use Rasuvaeff\JsonLd\NodeMap;
use Rasuvaeff\JsonLd\ProcessingOptions;
use Rasuvaeff\JsonLd\Rdf\BlankNode;
use Rasuvaeff\JsonLd\Rdf\Dataset;
use Rasuvaeff\JsonLd\Rdf\IdentifierIssuer;
use Rasuvaeff\JsonLd\Rdf\Iri as RdfIri;
use Rasuvaeff\JsonLd\Rdf\JsonCanonicalizer;
use Rasuvaeff\JsonLd\Rdf\Literal;
use Rasuvaeff\JsonLd\Rdf\NQuads;
use Rasuvaeff\JsonLd\Rdf\NQuadsWriter;
use Rasuvaeff\JsonLd\Rdf\Quad;
use Rasuvaeff\JsonLd\Rdf\Vocabulary;
use Rasuvaeff\JsonLd\Tests\Support\JsonLdDocuments;
use Rasuvaeff\PropertyTesting\Classify;
use Rasuvaeff\PropertyTesting\Gen;
use Rasuvaeff\PropertyTesting\Property;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

/**
 * Algebraic laws of the processor over generated documents: idempotency of
 * expansion and flattening, semantic preservation through flattening, and
 * the JSON/N-Quads round-trips.
 */
#[Test]
#[Covers(JsonLd::class)]
#[Covers(ExpansionRun::class)]
#[Covers(Json::class)]
#[Covers(Keywords::class)]
#[Covers(Iri::class)]
#[Covers(ProcessingOptions::class)]
#[Covers(ContextProcessor::class)]
#[Covers(ActiveContext::class)]
#[Covers(Syntax::class)]
#[Covers(TermDefinition::class)]
#[Covers(DefinitionScope::class)]
#[Covers(InlineContextLoader::class)]
#[Covers(MapContextLoader::class)]
#[Covers(LoadedContext::class)]
#[Covers(DocumentExpansion::class)]
#[Covers(Expander::class)]
#[Covers(Document::class)]
#[Covers(Graph::class)]
#[Covers(Node::class)]
#[Covers(Value::class)]
#[Covers(NodeMap::class)]
#[Covers(Flattener::class)]
#[Covers(Dataset::class)]
#[Covers(Quad::class)]
#[Covers(Literal::class)]
#[Covers(BlankNode::class)]
#[Covers(Vocabulary::class)]
#[Covers(NQuads::class)]
#[Covers(NQuadsWriter::class)]
#[Covers(JsonCanonicalizer::class)]
#[Covers(IdentifierIssuer::class)]
#[Covers(RdfIri::class)]
final class LawsPropertyTest
{
    #[Property(runs: 200)]
    public function expansionIsIdempotent(array $document): void
    {
        $once = JsonLd::expand($document);

        Assert::same(
            W3cExpandTest::canonical(JsonLd::expand($once)),
            W3cExpandTest::canonical($once),
        );
    }

    /** @return array<string, \Rasuvaeff\PropertyTesting\ArbitraryInterface> */
    public static function expansionIsIdempotentGenerators(): array
    {
        return ['document' => JsonLdDocuments::document()];
    }

    /** @return iterable<string, array{array}> */
    public static function expansionIsIdempotentExamples(): iterable
    {
        yield 'simple term' => [[['name' => 'value', '@context' => ['name' => 'http://example.com/name']]]];
        yield 'vocab and language' => [[['@context' => ['@vocab' => 'http://example.com/', '@language' => 'en'], 'p' => 'v']]];
    }

    #[Property(runs: 200)]
    public function flatteningIsIdempotent(array $document): void
    {
        $once = JsonLd::flatten($document);

        Assert::same(
            W3cExpandTest::canonical(JsonLd::flatten($once)),
            W3cExpandTest::canonical($once),
        );
    }

    /** @return array<string, \Rasuvaeff\PropertyTesting\ArbitraryInterface> */
    public static function flatteningIsIdempotentGenerators(): array
    {
        return ['document' => JsonLdDocuments::document()];
    }

    #[Property(runs: 200)]
    public function flatteningPreservesRdf(array $document): void
    {
        $direct = JsonLd::toRdf($document);
        $flattened = JsonLd::toRdf(JsonLd::flatten($document));

        Classify::cover(count($direct->quads) > 0, 'non-empty graph', 50);

        Assert::true($direct->equals($flattened));
    }

    /** @return array<string, \Rasuvaeff\PropertyTesting\ArbitraryInterface> */
    public static function flatteningPreservesRdfGenerators(): array
    {
        return ['document' => JsonLdDocuments::document()];
    }

    #[Property(runs: 200)]
    public function nquadsRoundTripKeepsDataset(string $quads): void
    {
        $dataset = JsonLd::parseNQuads($quads);

        Assert::true($dataset->equals(JsonLd::parseNQuads(JsonLd::serializeNQuads($dataset))));
    }

    /** @return array<string, \Rasuvaeff\PropertyTesting\ArbitraryInterface> */
    public static function nquadsRoundTripKeepsDatasetGenerators(): array
    {
        $iri = Gen::map(
            Gen::stringFrom('abcdefghij', minLength: 1, maxLength: 8),
            static fn(string $s): string => '<http://example.com/' . $s . '>',
        );

        $literal = Gen::map(
            Gen::record([
                'text' => Gen::stringFrom('abcdefghij ', minLength: 0, maxLength: 10),
                'lang' => Gen::nullable(Gen::elements(['en', 'de'])),
            ]),
            static fn(array $l): string => '"' . $l['text'] . '"'
                . ($l['lang'] !== null ? '@' . $l['lang'] : '^^<http://www.w3.org/2001/XMLSchema#string>'),
        );

        $quad = Gen::map(
            Gen::record(['s' => $iri, 'p' => $iri, 'o' => Gen::frequency([[1, $iri], [1, $literal]])]),
            static fn(array $q): string => $q['s'] . ' ' . $q['p'] . ' ' . $q['o'] . ' .',
        );

        return [
            'quads' => Gen::map(
                Gen::nonEmptyArrayOf($quad, maxSize: 6),
                static fn(array $lines): string => implode("\n", $lines),
            ),
        ];
    }

    /** @return iterable<string, array{string}> */
    public static function nquadsRoundTripKeepsDatasetExamples(): iterable
    {
        yield 'simple literal' => ["<http://a> <http://b> \"text\" ."];
        yield 'language literal' => ["<http://a> <http://b> \"text\"@en ."];
        yield 'blank node' => ["_:b0 <http://b> _:b1 ."];
    }

    #[Property(runs: 200)]
    public function jsonEncodeDecodeRoundTrip(array $value): void
    {
        $encoded = Json::encode($value);

        Assert::same(Json::decode($encoded), $value);
    }

    /** @return array<string, \Rasuvaeff\PropertyTesting\ArbitraryInterface> */
    public static function jsonEncodeDecodeRoundTripGenerators(): array
    {
        $scalar = Gen::frequency([
            [2, Gen::stringFrom('abcdefghij0123456789-_ ', minLength: 0, maxLength: 10)],
            [1, Gen::intBetween(-1000, 1000)],
            [1, Gen::bool()],
        ]);

        $nested = Gen::frequency([
            [1, Gen::nonEmptyArrayOf($scalar, maxSize: 4)],
            [1, Gen::dictOf(
                Gen::stringFrom('abcdefghij', minLength: 1, maxLength: 5),
                $scalar,
                minSize: 1,
                maxSize: 4,
            )],
        ]);

        return [
            'value' => Gen::frequency([
                [2, $nested],
                [1, Gen::nonEmptyArrayOf($nested, maxSize: 3)],
                [1, Gen::dictOf(
                    Gen::stringFrom('abcdefghij', minLength: 1, maxLength: 5),
                    $nested,
                    minSize: 1,
                    maxSize: 3,
                )],
            ]),
        ];
    }

    #[Property(runs: 100, timeoutMs: 500)]
    public function expansionNeverThrowsOnGeneratedDocuments(array $document): void
    {
        try {
            JsonLd::expand($document);
        } catch (JsonLdException $exception) {
            // Generated documents are valid by construction: any exception
            // is a processor bug worth a counterexample.
            Assert::same($exception->getMessage(), 'unreachable failure');
        }
    }

    /** @return array<string, \Rasuvaeff\PropertyTesting\ArbitraryInterface> */
    public static function expansionNeverThrowsOnGeneratedDocumentsGenerators(): array
    {
        return ['document' => JsonLdDocuments::document()];
    }
}
