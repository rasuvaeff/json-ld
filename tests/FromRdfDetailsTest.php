<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Exception\InvalidInputException;
use Rasuvaeff\JsonLd\Iri as IriCore;
use Rasuvaeff\JsonLd\Json;
use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\Keywords;
use Rasuvaeff\JsonLd\Rdf\BlankNode;
use Rasuvaeff\JsonLd\Rdf\Dataset;
use Rasuvaeff\JsonLd\Rdf\FromRdf;
use Rasuvaeff\JsonLd\Rdf\IdentifierIssuer;
use Rasuvaeff\JsonLd\Rdf\Iri;
use Rasuvaeff\JsonLd\Rdf\JsonCanonicalizer;
use Rasuvaeff\JsonLd\Rdf\Literal;
use Rasuvaeff\JsonLd\Rdf\NQuads;
use Rasuvaeff\JsonLd\Rdf\NQuadsWriter;
use Rasuvaeff\JsonLd\Rdf\Quad;
use Rasuvaeff\JsonLd\Rdf\Vocabulary;
use Rasuvaeff\JsonLd\RdfDirection;
use Rasuvaeff\JsonLd\Tests\Support\Strict;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(FromRdf::class)]
#[Covers(JsonLd::class)]
#[Covers(Json::class)]
#[Covers(Keywords::class)]
#[Covers(IriCore::class)]
#[Covers(Dataset::class)]
#[Covers(Quad::class)]
#[Covers(Literal::class)]
#[Covers(BlankNode::class)]
#[Covers(Vocabulary::class)]
#[Covers(NQuads::class)]
#[Covers(NQuadsWriter::class)]
#[Covers(JsonCanonicalizer::class)]
#[Covers(IdentifierIssuer::class)]
final class FromRdfDetailsTest
{
    private const string RDF = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';

    private const string XSD = 'http://www.w3.org/2001/XMLSchema#';

    public function convertsAnEmptyDataset(): void
    {
        Assert::same(JsonLd::fromRdf(new Dataset([])), []);
    }

    public function collapsesCompoundLiterals(): void
    {
        $dataset = new Dataset([
            $this->quad('http://s', 'http://p', '_:b'),
            $this->quad('_:b', self::RDF . 'value', new Literal('v', Vocabulary::XSD_STRING)),
            $this->quad('_:b', self::RDF . 'language', new Literal('en', Vocabulary::XSD_STRING)),
            $this->quad('_:b', self::RDF . 'direction', new Literal('rtl', Vocabulary::XSD_STRING)),
        ]);

        Assert::same(
            JsonLd::fromRdf($dataset, rdfDirection: RdfDirection::CompoundLiteral),
            [['@id' => 'http://s', 'http://p' => [['@value' => 'v', '@language' => 'en', '@direction' => 'rtl']]]],
        );
    }

    public function collapsesACompoundLiteralWithoutValueOrLanguage(): void
    {
        $dataset = new Dataset([
            $this->quad('http://s', 'http://p', '_:b'),
            $this->quad('_:b', self::RDF . 'direction', new Literal('ltr', Vocabulary::XSD_STRING)),
        ]);

        Assert::same(
            JsonLd::fromRdf($dataset, rdfDirection: RdfDirection::CompoundLiteral),
            [['@id' => 'http://s', 'http://p' => [['@value' => null, '@direction' => 'ltr']]]],
        );
    }

    public function keepsCompoundNodesThatAreUsedTwiceOrAreNotBlank(): void
    {
        $direction = new Literal('ltr', Vocabulary::XSD_STRING);

        $twice = JsonLd::fromRdf(
            new Dataset([
                $this->quad('http://s', 'http://p', '_:b'),
                $this->quad('http://s', 'http://q', '_:b'),
                $this->quad('_:b', self::RDF . 'direction', $direction),
            ]),
            rdfDirection: RdfDirection::CompoundLiteral,
        );

        Assert::same($twice, [
            ['@id' => '_:b', self::RDF . 'direction' => [['@value' => 'ltr']]],
            ['@id' => 'http://s', 'http://p' => [['@id' => '_:b']], 'http://q' => [['@id' => '_:b']]],
        ]);

        $iri = JsonLd::fromRdf(
            new Dataset([
                $this->quad('http://s', 'http://p', 'http://x'),
                $this->quad('http://x', self::RDF . 'direction', $direction),
            ]),
            rdfDirection: RdfDirection::CompoundLiteral,
        );

        Assert::same($iri, [
            ['@id' => 'http://s', 'http://p' => [['@id' => 'http://x']]],
            ['@id' => 'http://x', self::RDF . 'direction' => [['@value' => 'ltr']]],
        ]);
    }

    public function skipsASharedCompoundNodeAndStillCollapsesTheNext(): void
    {
        $direction = new Literal('ltr', Vocabulary::XSD_STRING);

        $result = JsonLd::fromRdf(
            new Dataset([
                $this->quad('http://s', 'http://p', '_:a'),
                $this->quad('http://s', 'http://q', '_:a'),
                $this->quad('_:a', self::RDF . 'direction', $direction),
                $this->quad('http://s', 'http://r', '_:b'),
                $this->quad('_:b', self::RDF . 'direction', $direction),
            ]),
            rdfDirection: RdfDirection::CompoundLiteral,
        );

        Assert::same($result, [
            ['@id' => '_:a', self::RDF . 'direction' => [['@value' => 'ltr']]],
            [
                '@id' => 'http://s',
                'http://p' => [['@id' => '_:a']],
                'http://q' => [['@id' => '_:a']],
                'http://r' => [['@value' => null, '@direction' => 'ltr']],
            ],
        ]);
    }

    public function ignoresCompoundShapesWithoutTheCompoundOption(): void
    {
        $result = JsonLd::fromRdf(new Dataset([
            $this->quad('http://s', 'http://p', '_:b'),
            $this->quad('_:b', self::RDF . 'direction', new Literal('ltr', Vocabulary::XSD_STRING)),
        ]));

        Assert::same(count($result), 2);
    }

    public function collapsesListsWhoseCellsArrivedInAnyPropertyOrder(): void
    {
        $dataset = new Dataset([
            $this->quad('http://s', 'http://p', '_:l'),
            $this->quad('_:l', self::RDF . 'rest', self::RDF . 'nil'),
            $this->quad('_:l', self::RDF . 'first', new Literal('a', Vocabulary::XSD_STRING)),
        ]);

        Assert::same(
            JsonLd::fromRdf($dataset),
            [['@id' => 'http://s', 'http://p' => [['@list' => [['@value' => 'a']]]]]],
        );
    }

    public function collapsesListCellsTypedAsRdfList(): void
    {
        $dataset = new Dataset([
            $this->quad('http://s', 'http://p', '_:l'),
            $this->quad('_:l', self::RDF . 'type', self::RDF . 'List'),
            $this->quad('_:l', self::RDF . 'first', new Literal('a', Vocabulary::XSD_STRING)),
            $this->quad('_:l', self::RDF . 'rest', self::RDF . 'nil'),
        ]);

        Assert::same(
            JsonLd::fromRdf($dataset),
            [['@id' => 'http://s', 'http://p' => [['@list' => [['@value' => 'a']]]]]],
        );
    }

    public function keepsListCellsWithOtherTypes(): void
    {
        $dataset = new Dataset([
            $this->quad('http://s', 'http://p', '_:l'),
            $this->quad('_:l', self::RDF . 'type', 'http://x/T'),
            $this->quad('_:l', self::RDF . 'first', new Literal('a', Vocabulary::XSD_STRING)),
            $this->quad('_:l', self::RDF . 'rest', self::RDF . 'nil'),
        ]);

        Assert::false(str_contains(json_encode(JsonLd::fromRdf($dataset), JSON_THROW_ON_ERROR), '@list":[{'));
    }

    public function sortsNodesAndGraphsById(): void
    {
        $dataset = new Dataset([
            $this->quad('http://z', 'http://p', new Literal('1', Vocabulary::XSD_STRING)),
            $this->quad('http://a', 'http://p', new Literal('2', Vocabulary::XSD_STRING)),
            $this->quad('http://s2', 'http://p', new Literal('3', Vocabulary::XSD_STRING), 'http://g/z'),
            $this->quad('http://s1', 'http://p', new Literal('4', Vocabulary::XSD_STRING), 'http://g/z'),
            $this->quad('http://s', 'http://p', new Literal('5', Vocabulary::XSD_STRING), 'http://g/a'),
        ]);

        $result = JsonLd::fromRdf($dataset);
        $ids = array_map(static fn(mixed $node): mixed => is_array($node) ? $node['@id'] : null, $result);

        Assert::same($ids, ['http://a', 'http://g/a', 'http://g/z', 'http://z']);

        $named = $result[2]['@graph'] ?? null;

        Assert::same(
            is_array($named) ? array_map(static fn(mixed $node): mixed => is_array($node) ? $node['@id'] : null, $named) : null,
            ['http://s1', 'http://s2'],
        );
    }

    public function convertsBooleansNatively(): void
    {
        $values = [];

        foreach (['true', 'false', '1', '0', 'yes'] as $lexical) {
            $values[] = $this->nativeValue(new Literal($lexical, self::XSD . 'boolean'));
        }

        Assert::same($values, [
            ['@value' => true],
            ['@value' => false],
            ['@value' => true],
            ['@value' => false],
            ['@value' => 'yes', '@type' => self::XSD . 'boolean'],
        ]);
    }

    public function convertsIntegersNativelyOnlyWhenTheyFit(): void
    {
        $cases = [
            '-9223372036854775808' => ['@value' => PHP_INT_MIN],
            '9223372036854775807' => ['@value' => PHP_INT_MAX],
            '9223372036854775808' => ['@value' => '9223372036854775808', '@type' => self::XSD . 'integer'],
            '-9223372036854775809' => ['@value' => '-9223372036854775809', '@type' => self::XSD . 'integer'],
            '-0' => ['@value' => 0],
            '+7' => ['@value' => 7],
            '007' => ['@value' => 7],
            '-007' => ['@value' => -7],
            'x12' => ['@value' => 'x12', '@type' => self::XSD . 'integer'],
            'abc' => ['@value' => 'abc', '@type' => self::XSD . 'integer'],
        ];

        foreach ($cases as $lexical => $expected) {
            Assert::same($this->nativeValue(new Literal((string) $lexical, self::XSD . 'integer')), $expected);
        }
    }

    public function convertsNativeTypesWithoutPhpDiagnostics(): void
    {
        $values = Strict::run(fn(): array => [
            $this->nativeValue(new Literal('abc', self::XSD . 'integer')),
            $this->nativeValue(new Literal('12', self::XSD . 'integer')),
            $this->nativeValue(new Literal('nope', self::XSD . 'double')),
        ]);

        Assert::same($values, [
            ['@value' => 'abc', '@type' => self::XSD . 'integer'],
            ['@value' => 12],
            ['@value' => 'nope', '@type' => self::XSD . 'double'],
        ]);
    }

    public function convertsDoublesNativelyOnlyWhenWellFormed(): void
    {
        $cases = [
            '1.5' => ['@value' => 1.5],
            '-2e3' => ['@value' => -2000.0],
            '.5' => ['@value' => 0.5],
            'x1.5' => ['@value' => 'x1.5', '@type' => self::XSD . 'double'],
            '1.5x' => ['@value' => '1.5x', '@type' => self::XSD . 'double'],
            '1e999' => ['@value' => '1e999', '@type' => self::XSD . 'double'],
        ];

        foreach ($cases as $lexical => $expected) {
            Assert::same($this->nativeValue(new Literal((string) $lexical, self::XSD . 'double')), $expected);
        }
    }

    public function parsesJsonLiteralsUpToTheDepthLimit(): void
    {
        $deepest = str_repeat('[', 511) . str_repeat(']', 511);
        $tooDeep = str_repeat('[', 512) . str_repeat(']', 512);

        foreach ([false, true] as $native) {
            $dataset = new Dataset([$this->quad('http://s', 'http://p', new Literal($deepest, Vocabulary::RDF_JSON))]);
            $result = JsonLd::fromRdf($dataset, useNativeTypes: $native);

            Assert::same(count($result), 1);

            $message = null;

            try {
                JsonLd::fromRdf(
                    new Dataset([$this->quad('http://s', 'http://p', new Literal($tooDeep, Vocabulary::RDF_JSON))]),
                    useNativeTypes: $native,
                );
            } catch (InvalidInputException $exception) {
                $message = $exception->getMessage();
            }

            Assert::same($message, 'invalid JSON literal');
        }
    }

    public function splitsI18nDatatypesAtTheFirstUnderscoreOnly(): void
    {
        $dataset = new Dataset([
            $this->quad('http://s', 'http://p', new Literal('v', Vocabulary::I18N . 'en_a_b')),
            $this->quad('http://s', 'http://q', new Literal('w', Vocabulary::I18N . '_rtl')),
        ]);

        Assert::same(
            JsonLd::fromRdf($dataset, rdfDirection: RdfDirection::I18nDatatype),
            [[
                '@id' => 'http://s',
                'http://p' => [['@language' => 'en', '@direction' => 'a_b', '@value' => 'v']],
                'http://q' => [['@direction' => 'rtl', '@value' => 'w']],
            ]],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function nativeValue(Literal $literal): array
    {
        $result = JsonLd::fromRdf(new Dataset([$this->quad('http://s', 'http://p', $literal)]), useNativeTypes: true);
        $node = $result[0];
        $values = is_array($node) ? ($node['http://p'] ?? null) : null;
        $value = is_array($values) ? ($values[0] ?? null) : null;

        return is_array($value) ? $value : [];
    }

    private function quad(string $subject, string $predicate, Literal|string $object, ?string $graph = null): Quad
    {
        return new Quad(
            subject: $this->term($subject),
            predicate: new Iri($predicate),
            object: $object instanceof Literal ? $object : $this->term($object),
            graph: $graph === null ? null : $this->term($graph),
        );
    }

    private function term(string $value): Iri|BlankNode
    {
        return str_starts_with($value, '_:') ? new BlankNode(substr($value, 2)) : new Iri($value);
    }
}
