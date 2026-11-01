<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Random\Engine\Mt19937;
use Random\Randomizer;
use Rasuvaeff\JsonLd\Exception\CanonicalizationLimitException;
use Rasuvaeff\JsonLd\Exception\ErrorCode;
use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\Rdf\BlankNode;
use Rasuvaeff\JsonLd\Rdf\CanonicalForm;
use Rasuvaeff\JsonLd\Rdf\CanonicalizationRun;
use Rasuvaeff\JsonLd\Rdf\Canonicalizer;
use Rasuvaeff\JsonLd\Rdf\Dataset;
use Rasuvaeff\JsonLd\Rdf\HashAlgorithm;
use Rasuvaeff\JsonLd\Rdf\IdentifierIssuer;
use Rasuvaeff\JsonLd\Rdf\Iri;
use Rasuvaeff\JsonLd\Rdf\JsonCanonicalizer;
use Rasuvaeff\JsonLd\Rdf\Literal;
use Rasuvaeff\JsonLd\Rdf\NQuads;
use Rasuvaeff\JsonLd\Rdf\NQuadsWriter;
use Rasuvaeff\JsonLd\Rdf\Quad;
use Rasuvaeff\JsonLd\Rdf\Vocabulary;
use Rasuvaeff\JsonLd\Tests\Support\DatasetGenerators;
use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Classify;
use Rasuvaeff\PropertyTesting\Property;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(Canonicalizer::class)]
#[Covers(CanonicalizationRun::class)]
#[Covers(CanonicalForm::class)]
#[Covers(IdentifierIssuer::class)]
#[Covers(HashAlgorithm::class)]
#[Covers(Dataset::class)]
#[Covers(JsonLd::class)]
#[Covers(Quad::class)]
#[Covers(Literal::class)]
#[Covers(BlankNode::class)]
#[Covers(Vocabulary::class)]
#[Covers(NQuads::class)]
#[Covers(NQuadsWriter::class)]
#[Covers(JsonCanonicalizer::class)]
#[Covers(Iri::class)]
final class CanonicalizerTest
{
    public function issuesCanonicalLabelsInOrder(): void
    {
        $form = (new Canonicalizer())->canonicalize(JsonLd::parseNQuads(
            "_:0 <http://p> _:1 .\n_:1 <http://q> \"x\" .\n_:1 <http://q> \"x\" .\n",
        ));

        // labels that look like integers stay strings; issue order follows the first-degree hashes
        Assert::same($form->nquads, "_:c14n0 <http://q> \"x\" .\n_:c14n1 <http://p> _:c14n0 .\n");
        Assert::same($form->issuedIdentifiers, ['_:1' => '_:c14n0', '_:0' => '_:c14n1']);
        Assert::same($form->hashAlgorithm, HashAlgorithm::Sha256);
        Assert::same($form->hash(), hash('sha256', $form->nquads));
    }

    public function hashesWithTheChosenAlgorithm(): void
    {
        $dataset = JsonLd::parseNQuads("_:a <http://p> _:b .\n_:b <http://p> _:a .\n");
        $form = (new Canonicalizer(hashAlgorithm: HashAlgorithm::Sha384))->canonicalize($dataset);

        Assert::same($form->hash(), hash('sha384', $form->nquads));
        Assert::same(strlen($form->hash()), 96);
        Assert::same(HashAlgorithm::Sha256->hash('abc'), hash('sha256', 'abc'));
    }

    public function anEmptyDatasetHasAnEmptyCanonicalForm(): void
    {
        $dataset = new Dataset([]);

        Assert::same($dataset->canonicalNQuads(), '');
        Assert::same($dataset->canonicalHash(), hash('sha256', ''));
    }

    public function theWorkFactorBoundsPoisonGraphs(): void
    {
        // test044 of the W3C suite: twelve blank nodes, 43 Hash N-Degree Quads runs for the busiest one
        $poison = JsonLd::parseNQuads((string) file_get_contents(__DIR__ . '/fixtures/rdf-canon/rdfc10/test044-in.nq'));
        $code = null;
        $message = null;

        try {
            (new Canonicalizer(workFactor: 3))->canonicalize($poison);
        } catch (CanonicalizationLimitException $exception) {
            $code = $exception->errorCode();
            $message = $exception->getMessage();
        }

        Assert::same($code, ErrorCode::CanonicalizationLimitExceeded);
        Assert::same($message, 'canonicalization limit exceeded: Hash N-Degree Quads ran more than 36 times for one blank node');
        Assert::same(
            (new Canonicalizer(workFactor: 4))->canonicalize($poison)->nquads,
            (string) file_get_contents(__DIR__ . '/fixtures/rdf-canon/rdfc10/test044-rdfc10.nq'),
        );
        Assert::same(Canonicalizer::DEFAULT_WORK_FACTOR, 4);
    }

    public function aCycleNeedsOneRunPerBlankNode(): void
    {
        $rows = '';

        for ($index = 0; $index < 30; $index++) {
            $rows .= sprintf("_:n%d <http://p> _:n%d .\n", $index, ($index + 1) % 30);
        }

        $form = (new Canonicalizer(workFactor: 1))->canonicalize(JsonLd::parseNQuads($rows));

        Assert::same(count($form->issuedIdentifiers), 30);
    }

    public function blankNodesAlreadyLabelledByAnEarlierGroupDoNotStopTheirGroup(): void
    {
        // _:n0 is unique; _:n2 and _:n1 share a hash, so do _:n6 and _:n4: labelling the first
        // chain reaches _:n6 before its group comes up, and _:n4 must still get its label
        $form = (new Canonicalizer())->canonicalize(JsonLd::parseNQuads(
            "_:n2 <http://p1> _:n0 .\n_:n1 <http://p1> _:n4 .\n_:n0 <http://p1> _:n6 .\n",
        ));

        Assert::same($form->nquads, "_:c14n0 <http://p1> _:c14n4 .\n_:c14n1 <http://p1> _:c14n0 .\n_:c14n2 <http://p1> _:c14n3 .\n");
        Assert::same(
            $form->issuedIdentifiers,
            ['_:n0' => '_:c14n0', '_:n2' => '_:c14n1', '_:n1' => '_:c14n2', '_:n4' => '_:c14n3', '_:n6' => '_:c14n4'],
        );
    }

    public function theLimitIsTheWorkFactorTimesTheNonUniqueBlankNodes(): void
    {
        // three non-unique blank nodes, the busiest one needs four Hash N-Degree Quads runs
        $dataset = JsonLd::parseNQuads(
            "_:n3 <http://p0> _:n4 .\n_:n2 <http://p0> _:n4 .\n_:n2 <http://p0> _:n1 .\n"
            . "_:n3 <http://p0> _:n0 .\n_:n0 <http://p0> _:n3 .\n_:n0 <http://p0> _:n2 .\n",
        );
        $message = null;

        try {
            (new Canonicalizer(workFactor: 1))->canonicalize($dataset);
        } catch (CanonicalizationLimitException $exception) {
            $message = $exception->getMessage();
        }

        Assert::same($message, 'canonicalization limit exceeded: Hash N-Degree Quads ran more than 3 times for one blank node');
        Assert::same(count((new Canonicalizer(workFactor: 2))->canonicalize($dataset)->issuedIdentifiers), 5);
    }

    public function relabellingNeverChangesTheFormEvenWhenNamedGraphRelationsTie(): void
    {
        // _:n0 and _:n1 relate to the same two nodes, one relation in <http://g2>, the other
        // in the default graph, but the other way round: Hash Related Blank Node does not
        // include the graph name, so their N-degree hashes tie
        $rows = DatasetGenerators::namedGraphTie();
        $expected = JsonLd::parseNQuads(DatasetGenerators::nquads($rows))->canonicalNQuads();

        for ($seed = 1; $seed <= 12; $seed++) {
            $randomizer = new Randomizer(new Mt19937($seed));
            $labels = array_combine(
                DatasetGenerators::LABELS,
                array_map(static fn(string $label): string => 'r' . $label, $randomizer->shuffleArray(DatasetGenerators::LABELS)),
            );

            Assert::same(JsonLd::parseNQuads(DatasetGenerators::nquads($rows, $labels))->canonicalNQuads(), $expected);
        }
    }

    public function knownLimitationQuadOrderBreaksNamedGraphTies(): void
    {
        // RDFC-1.0 as specified: the tie above is broken by the order of the input, so the
        // same dataset in another quad order gets another (equally valid) canonical form
        $rows = DatasetGenerators::namedGraphTie();
        $reordered = [$rows[4], $rows[0], $rows[1], $rows[2], $rows[3], $rows[5]];

        $original = JsonLd::parseNQuads(DatasetGenerators::nquads($rows));
        $shuffled = JsonLd::parseNQuads(DatasetGenerators::nquads($reordered));

        Assert::false($original->canonicalNQuads() === $shuffled->canonicalNQuads());
        Assert::false($original->equals($shuffled));
    }

    public function theWorkFactorMustBePositive(): void
    {
        $message = null;

        try {
            new Canonicalizer(workFactor: 0);
        } catch (\InvalidArgumentException $exception) {
            $message = $exception->getMessage();
        }

        Assert::same($message, 'Work factor must be positive, 0 given');
        Assert::same(count((new Canonicalizer(workFactor: 1))->canonicalize(JsonLd::parseNQuads("_:a <http://p> _:b .\n_:b <http://p> _:a .\n"))->issuedIdentifiers), 2);
    }

    #[Property(runs: 150)]
    public function canonicalFormIgnoresLabelsAndDuplicates(array $dataset, int $seed): void
    {
        $randomizer = new Randomizer(new Mt19937($seed));
        $rows = $dataset['rows'];
        $labels = array_combine(
            DatasetGenerators::LABELS,
            array_map(static fn(string $label): string => 'r' . $label, $randomizer->shuffleArray(DatasetGenerators::LABELS)),
        );

        $original = JsonLd::parseNQuads(DatasetGenerators::nquads($rows));
        $renamed = JsonLd::parseNQuads(DatasetGenerators::nquads([...$rows, $rows[0]], $labels));

        Classify::cover($dataset['kind'] === 'cycle', 'symmetric blank nodes', 10);
        Classify::cover($dataset['kind'] === 'random', 'random rows', 50);

        Assert::same($renamed->canonicalNQuads(), $original->canonicalNQuads());
        Assert::same($renamed->canonicalHash(), $original->canonicalHash());
        Assert::true($renamed->equals($original));
    }

    /** @return array<string, ArbitraryInterface> */
    public static function canonicalFormIgnoresLabelsAndDuplicatesGenerators(): array
    {
        return ['dataset' => DatasetGenerators::dataset(), 'seed' => DatasetGenerators::seed()];
    }

    /** @return iterable<string, array{array, int}> */
    public static function canonicalFormIgnoresLabelsAndDuplicatesExamples(): iterable
    {
        yield 'symmetric pair' => [['kind' => 'cycle', 'rows' => [['_:n0', '<http://p>', '_:n1', ''], ['_:n1', '<http://p>', '_:n0', '']]], 7];
        yield 'blank graph name' => [['kind' => 'random', 'rows' => [['_:n0', '<http://p>', '_:n1', '_:n4'], ['_:n4', '<http://q>', '"x"', '']]], 3];
        yield 'named graph tie' => [['kind' => 'random', 'rows' => DatasetGenerators::namedGraphTie()], 5];
    }

    #[Property(runs: 150)]
    public function canonicalFormOfTriplesIgnoresStatementOrder(array $dataset, int $seed): void
    {
        $randomizer = new Randomizer(new Mt19937($seed));
        $rows = array_map(static fn(array $row): array => [$row[0], $row[1], $row[2], ''], $dataset['rows']);
        $labels = array_combine(
            DatasetGenerators::LABELS,
            array_map(static fn(string $label): string => 'r' . $label, $randomizer->shuffleArray(DatasetGenerators::LABELS)),
        );

        $original = JsonLd::parseNQuads(DatasetGenerators::nquads($rows));
        $shuffled = JsonLd::parseNQuads(DatasetGenerators::nquads($randomizer->shuffleArray([...$rows, $rows[0]]), $labels));

        Classify::cover($dataset['kind'] === 'cycle', 'symmetric blank nodes', 10);

        Assert::same($shuffled->canonicalNQuads(), $original->canonicalNQuads());
        Assert::true($shuffled->equals($original));
    }

    /** @return array<string, ArbitraryInterface> */
    public static function canonicalFormOfTriplesIgnoresStatementOrderGenerators(): array
    {
        return ['dataset' => DatasetGenerators::dataset(), 'seed' => DatasetGenerators::seed()];
    }

    /** @return iterable<string, array{array, int}> */
    public static function canonicalFormOfTriplesIgnoresStatementOrderExamples(): iterable
    {
        yield 'symmetric pair' => [['kind' => 'cycle', 'rows' => [['_:n0', '<http://p>', '_:n1', ''], ['_:n1', '<http://p>', '_:n0', '']]], 7];
        yield 'self loop' => [['kind' => 'random', 'rows' => [['_:n2', '<http://p>', '_:n2', '']]], 11];
    }

    #[Property(runs: 150)]
    public function canonicalFormIsAFixedPoint(array $dataset): void
    {
        $canonical = JsonLd::parseNQuads(DatasetGenerators::nquads($dataset['rows']))->canonicalNQuads();
        $form = (new Canonicalizer())->canonicalize(JsonLd::parseNQuads($canonical));

        Classify::cover(str_contains($canonical, '_:c14n'), 'has blank nodes', 50);

        Assert::same($form->nquads, $canonical);

        foreach ($form->issuedIdentifiers as $label => $issued) {
            Assert::same($issued, $label);
        }
    }

    /** @return array<string, ArbitraryInterface> */
    public static function canonicalFormIsAFixedPointGenerators(): array
    {
        return ['dataset' => DatasetGenerators::dataset()];
    }

    #[Property(runs: 100)]
    public function anExtraStatementBreaksEquality(array $dataset): void
    {
        $original = JsonLd::parseNQuads(DatasetGenerators::nquads($dataset['rows']));
        $extended = JsonLd::parseNQuads(DatasetGenerators::nquads($dataset['rows']) . "<http://e/z> <http://p> \"unique\" .\n");

        Assert::false($extended->equals($original));
        Assert::false($extended->canonicalHash() === $original->canonicalHash());
    }

    /** @return array<string, ArbitraryInterface> */
    public static function anExtraStatementBreaksEqualityGenerators(): array
    {
        return ['dataset' => DatasetGenerators::dataset()];
    }
}
