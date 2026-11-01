<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Context\ActiveContext;
use Rasuvaeff\JsonLd\Context\ContextProcessor;
use Rasuvaeff\JsonLd\Context\DefinitionScope;
use Rasuvaeff\JsonLd\Context\Syntax;
use Rasuvaeff\JsonLd\Context\TermDefinition;
use Rasuvaeff\JsonLd\DocumentExpansion;
use Rasuvaeff\JsonLd\Expander;
use Rasuvaeff\JsonLd\ExpansionRun;
use Rasuvaeff\JsonLd\Flattener;
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
use Rasuvaeff\JsonLd\Rdf\ToRdf;
use Rasuvaeff\JsonLd\Rdf\Vocabulary;
use Rasuvaeff\JsonLd\RdfDirection;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(ToRdf::class)]
#[Covers(ExpansionRun::class)]
#[Covers(JsonLd::class)]
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
final class ToRdfDetailsTest
{
    private const string FIRST = '<http://www.w3.org/1999/02/22-rdf-syntax-ns#first>';

    private const string REST = '<http://www.w3.org/1999/02/22-rdf-syntax-ns#rest>';

    private const string NIL = '<http://www.w3.org/1999/02/22-rdf-syntax-ns#nil>';

    private const string DOUBLE = '<http://www.w3.org/2001/XMLSchema#double>';

    public function emitsGraphsSubjectsAndPropertiesInSortedOrder(): void
    {
        $dataset = JsonLd::toRdf([
            '@graph' => [
                ['@id' => 'http://g/z', '@graph' => [
                    ['@id' => 'http://s/b', 'http://p/2' => 'x', 'http://p/1' => 'y'],
                    ['@id' => 'http://s/a', 'http://p/1' => 'z'],
                ]],
                ['@id' => 'http://g/a', '@graph' => [['@id' => 'http://s/b', 'http://p/1' => 'w']]],
                ['@id' => 'http://s/b', 'http://p/2' => 'd'],
            ],
        ]);

        Assert::same(
            JsonLd::serializeNQuads($dataset),
            <<<'NQ'
                <http://s/b> <http://p/2> "d" .
                <http://s/b> <http://p/1> "w" <http://g/a> .
                <http://s/a> <http://p/1> "z" <http://g/z> .
                <http://s/b> <http://p/1> "y" <http://g/z> .
                <http://s/b> <http://p/2> "x" <http://g/z> .

                NQ,
        );
    }

    public function skipsIllFormedGraphNamesAndSubjectsWithoutStoppingTheRun(): void
    {
        $dataset = JsonLd::toRdf([
            '@graph' => [
                ['@id' => 'a', '@graph' => [['@id' => 'http://s/1', 'http://p/1' => 'bad']]],
                ['@id' => 'http://z', '@graph' => [['@id' => 'http://s/2', 'http://p/1' => 'good']]],
                ['@id' => 'a', 'http://p/1' => 'x'],
                ['@id' => 'http://s/3', 'http://p/1' => 'y'],
            ],
        ]);

        Assert::same(
            JsonLd::serializeNQuads($dataset),
            "<http://s/3> <http://p/1> \"y\" .\n<http://s/2> <http://p/1> \"good\" <http://z> .\n",
        );
    }

    public function dropsBlankNodePropertiesUnlessGeneralizedRdfIsProduced(): void
    {
        $document = ['@id' => 'http://s', '_:p' => 'x', 'http://q' => 'y'];

        Assert::same(JsonLd::serializeNQuads(JsonLd::toRdf($document)), "<http://s> <http://q> \"y\" .\n");
        Assert::same(
            JsonLd::serializeNQuads(JsonLd::toRdf($document, produceGeneralizedRdf: true)),
            "<http://s> <_:b0> \"x\" .\n<http://s> <http://q> \"y\" .\n",
        );
    }

    public function convertsListsToLinkedBlankNodes(): void
    {
        $dataset = JsonLd::toRdf(['@id' => 'http://s', 'http://p' => ['@list' => ['a', 'b']]]);

        Assert::same(
            JsonLd::serializeNQuads($dataset),
            '_:b0 ' . self::FIRST . ' "a" .' . "\n"
            . '_:b0 ' . self::REST . ' _:b1 .' . "\n"
            . '_:b1 ' . self::FIRST . ' "b" .' . "\n"
            . '_:b1 ' . self::REST . ' ' . self::NIL . ' .' . "\n"
            . '<http://s> <http://p> _:b0 .' . "\n",
        );
    }

    public function convertsAnEmptyListToNil(): void
    {
        $dataset = JsonLd::toRdf(['@id' => 'http://s', 'http://p' => ['@list' => []]]);

        Assert::same(JsonLd::serializeNQuads($dataset), '<http://s> <http://p> ' . self::NIL . " .\n");
    }

    public function convertsCompoundLiterals(): void
    {
        $dataset = JsonLd::toRdf(
            ['@id' => 'http://s', 'http://p' => ['@value' => 'v', '@language' => 'EN', '@direction' => 'rtl']],
            rdfDirection: RdfDirection::CompoundLiteral,
        );

        Assert::same(
            JsonLd::serializeNQuads($dataset),
            '_:b0 <http://www.w3.org/1999/02/22-rdf-syntax-ns#value> "v" .' . "\n"
            . '_:b0 <http://www.w3.org/1999/02/22-rdf-syntax-ns#language> "en" .' . "\n"
            . '_:b0 <http://www.w3.org/1999/02/22-rdf-syntax-ns#direction> "rtl" .' . "\n"
            . '<http://s> <http://p> _:b0 .' . "\n",
        );
    }

    public function relabelsBlankNodesWithoutThePrefix(): void
    {
        $dataset = JsonLd::toRdf(['@id' => '_:x', 'http://p' => ['@id' => '_:y'], 'http://q' => ['@id' => '_:x']]);

        Assert::same(
            JsonLd::serializeNQuads($dataset),
            "_:b0 <http://p> _:b1 .\n_:b0 <http://q> _:b0 .\n",
        );
    }

    public function convertsNumbersByDatatype(): void
    {
        $dataset = JsonLd::toRdf([
            '@id' => 'http://s',
            'http://p' => [
                ['@value' => 'abc', '@type' => 'http://www.w3.org/2001/XMLSchema#double'],
                ['@value' => 5.0, '@type' => 'http://www.w3.org/2001/XMLSchema#double'],
                ['@value' => 1, '@type' => 'http://www.w3.org/2001/XMLSchema#double'],
                ['@value' => 5.0],
            ],
        ]);

        Assert::same(
            JsonLd::serializeNQuads($dataset),
            '<http://s> <http://p> "abc"^^' . self::DOUBLE . " .\n"
            . '<http://s> <http://p> "5.0E0"^^' . self::DOUBLE . " .\n"
            . '<http://s> <http://p> "1.0E0"^^' . self::DOUBLE . " .\n"
            . '<http://s> <http://p> "5"^^<http://www.w3.org/2001/XMLSchema#integer> .' . "\n",
        );
    }
}
