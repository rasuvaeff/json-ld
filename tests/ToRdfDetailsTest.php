<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\Rdf\ToRdf;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(ToRdf::class)]
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
            rdfDirection: 'compound-literal',
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
