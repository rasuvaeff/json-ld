<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Exception\InvalidNQuadsException;
use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\Rdf\BlankNode;
use Rasuvaeff\JsonLd\Rdf\Dataset;
use Rasuvaeff\JsonLd\Rdf\Iri;
use Rasuvaeff\JsonLd\Rdf\Literal;
use Rasuvaeff\JsonLd\Rdf\NQuads;
use Rasuvaeff\JsonLd\Rdf\Quad;
use Rasuvaeff\JsonLd\Tests\Support\Strict;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(NQuads::class)]
final class NQuadsSyntaxTest
{
    public function reportsTheOneBasedLineNumber(): void
    {
        Assert::same($this->failure("<http://a> <http://b> <http://c> .\n# comment\n\nbad"), 'Invalid N-Quads term at line 4');
        Assert::same($this->failure('bad'), 'Invalid N-Quads term at line 1');
        Assert::same($this->failure("\n<http://a> <http://b>"), 'Missing "." at line 2');
        Assert::same($this->failure("<http://a> <http://b> <http://c> . x\n"), 'Unexpected content after "." at line 1');
        Assert::same($this->failure("\n\n<http://a> <http://b> ."), 'Incomplete statement at line 3');
        Assert::same($this->failure('"x" <http://b> <http://c> .'), 'Invalid term position at line 1');
    }

    public function trimsSpacesAndTabsAroundLines(): void
    {
        $dataset = JsonLd::parseNQuads("  # comment\n \t \n\t<http://a> <http://b> <http://c> .\t \n");

        Assert::same(count($dataset->quads), 1);
    }

    public function rejectsMoreThanFourTerms(): void
    {
        Assert::same($this->failure('<http://a> <http://b> <http://c> <http://d> <http://e> .'), 'Missing "." at line 1');
    }

    public function rejectsAStatementWithoutTheClosingDot(): void
    {
        Assert::same($this->failure('<http://a> <http://b>'), 'Missing "." at line 1');
        Assert::same($this->failure('<http://a> <http://b> <http://c>'), 'Missing "." at line 1');
    }

    public function toleratesWhitespaceAroundTheDot(): void
    {
        $lines = [
            "<http://a>  \t<http://b>\t <http://c>\t.",
            '<http://a> <http://b> <http://c> <http://d> .',
            '<http://a> <http://b> <http://c> <http://d>.',
            "<http://a> <http://b> <http://c> <http://d> \t .",
            '<http://a> <http://b> <http://c> .#comment',
            '<http://a> <http://b> <http://c> . #comment',
        ];

        foreach ($lines as $line) {
            Assert::same(count(JsonLd::parseNQuads($line)->quads), 1);
        }
    }

    public function parsesTheGraphTerm(): void
    {
        $named = JsonLd::parseNQuads('<http://a> <http://b> <http://c> <http://g> .')->quads[0];
        $blank = JsonLd::parseNQuads('<http://a> <http://b> <http://c> _:g .')->quads[0];
        $none = JsonLd::parseNQuads('<http://a> <http://b> <http://c> .')->quads[0];

        Assert::instanceOf($named->graph, Iri::class);
        Assert::same($named->graph->value, 'http://g');
        Assert::instanceOf($blank->graph, BlankNode::class);
        Assert::same($blank->graph->id, 'g');
        Assert::null($none->graph);
    }

    public function rejectsLiteralGraphs(): void
    {
        Assert::same($this->failure('<http://a> <http://b> <http://c> "g" .'), 'Invalid term position at line 1');
    }

    public function parsesBlankNodesAndKeepsTheOffset(): void
    {
        $quad = JsonLd::parseNQuads('_:s1 <http://b> _:o2 .')->quads[0];

        Assert::instanceOf($quad->subject, BlankNode::class);
        Assert::same($quad->subject->id, 's1');
        Assert::instanceOf($quad->object, BlankNode::class);
        Assert::same($quad->object->id, 'o2');
    }

    public function parsesLiteralKinds(): void
    {
        $plain = JsonLd::parseNQuads('<http://a> <http://b> "x" .')->quads[0]->object;
        $lang = JsonLd::parseNQuads('<http://a> <http://b> "x"@en-GB .')->quads[0]->object;
        $typed = JsonLd::parseNQuads('<http://a> <http://b> "x"^^<http://dt> .')->quads[0]->object;
        $string = JsonLd::parseNQuads('<http://a> <http://b> "x"^^<' . Literal::XSD_STRING . '> .')->quads[0]->object;

        Assert::same([$plain->value, $plain->datatype, $plain->language], ['x', Literal::XSD_STRING, null]);
        Assert::same($lang->language, 'en-GB');
        Assert::same($lang->datatype, Literal::RDF_LANGSTRING);
        Assert::same($typed->datatype, 'http://dt');
        Assert::null($typed->language);
        Assert::same([$string->value, $string->datatype, $string->language], ['x', Literal::XSD_STRING, null]);
    }

    public function parsesEmptyAndTypedLiteralsWithoutPhpDiagnostics(): void
    {
        $objects = Strict::run(static fn(): array => [
            JsonLd::parseNQuads('<http://a> <http://b> "" .')->quads[0]->object,
            JsonLd::parseNQuads('<http://a> <http://b> ""^^<http://dt> .')->quads[0]->object,
            JsonLd::parseNQuads('<http://a> <http://b> ""@en .')->quads[0]->object,
            JsonLd::parseNQuads('<http://a> <http://b> "x"^^<http://dt> .')->quads[0]->object,
            JsonLd::parseNQuads('<http://a> <http://b> "x"@fr .')->quads[0]->object,
            JsonLd::parseNQuads('<http://a> <http://b> "\\u4E2D\\U0001F600" .')->quads[0]->object,
        ]);

        Assert::same(
            array_map(static fn(Literal $literal): array => [$literal->value, $literal->datatype, $literal->language], $objects),
            [
                ['', Literal::XSD_STRING, null],
                ['', 'http://dt', null],
                ['', Literal::RDF_LANGSTRING, 'en'],
                ['x', 'http://dt', null],
                ['x', Literal::RDF_LANGSTRING, 'fr'],
                ["\u{4E2D}\u{1F600}", Literal::XSD_STRING, null],
            ],
        );
    }

    public function unescapesEveryEscapeSequence(): void
    {
        $object = JsonLd::parseNQuads('<http://a> <http://b> "\\t\\b\\n\\r\\f\\"\\\'\\\\\\u00E9\\U0001F600" .')->quads[0]->object;

        Assert::same($object->value, "\t\x08\n\r\x0C\"'\\\u{E9}\u{1F600}");
    }

    public function unescapesIriReferences(): void
    {
        $subject = JsonLd::parseNQuads('<http://a/\\u00E9> <http://b> <http://c> .')->quads[0]->subject;

        Assert::same($subject->value, "http://a/\u{E9}");
    }

    public function escapesEveryControlCharacterWhenSerializing(): void
    {
        $dataset = new Dataset([
            new Quad(new Iri('http://a'), new Iri('http://b'), Literal::string("\x08\t\n\x0C\r\"\\\x01\x1F\x7F")),
        ]);

        Assert::same(
            JsonLd::serializeNQuads($dataset),
            '<http://a> <http://b> "\\b\\t\\n\\f\\r\\"\\\\\\u0001\\u001F\\u007F" .' . "\n",
        );
    }

    public function keepsIriCharactersUnescapedWhenSerializing(): void
    {
        $dataset = new Dataset([
            new Quad(new Iri('http://a/b\\c'), new Iri('http://b'), Literal::typed('x', 'http://d/e\\f')),
        ]);

        Assert::same(
            JsonLd::serializeNQuads($dataset),
            '<http://a/b\\c> <http://b> "x"^^<http://d/e\\f> .' . "\n",
        );
    }

    public function serializesTermKinds(): void
    {
        $dataset = new Dataset([
            new Quad(new BlankNode('s'), new Iri('http://p'), Literal::langString('x', 'en'), new BlankNode('g')),
            new Quad(new Iri('http://s'), new Iri('http://p'), Literal::string('y')),
        ]);

        Assert::same(
            JsonLd::serializeNQuads($dataset),
            "_:s <http://p> \"x\"@en _:g .\n<http://s> <http://p> \"y\" .\n",
        );
        Assert::same(JsonLd::serializeNQuads(new Dataset([])), '');
    }

    private function failure(string $text): ?string
    {
        try {
            JsonLd::parseNQuads($text);
        } catch (InvalidNQuadsException $exception) {
            return $exception->getMessage();
        }

        return null;
    }
}
