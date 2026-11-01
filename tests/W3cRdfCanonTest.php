<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Exception\CanonicalizationLimitException;
use Rasuvaeff\JsonLd\Exception\ErrorCode;
use Rasuvaeff\JsonLd\Json;
use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\Rdf\BlankNode;
use Rasuvaeff\JsonLd\Rdf\CanonicalForm;
use Rasuvaeff\JsonLd\Rdf\CanonicalizationRun;
use Rasuvaeff\JsonLd\Rdf\Canonicalizer;
use Rasuvaeff\JsonLd\Rdf\Dataset;
use Rasuvaeff\JsonLd\Rdf\HashAlgorithm;
use Rasuvaeff\JsonLd\Rdf\IdentifierIssuer;
use Rasuvaeff\JsonLd\Rdf\Iri;
use Rasuvaeff\JsonLd\Rdf\Literal;
use Rasuvaeff\JsonLd\Rdf\NQuads;
use Rasuvaeff\JsonLd\Rdf\NQuadsWriter;
use Rasuvaeff\JsonLd\Rdf\Quad;
use Rasuvaeff\JsonLd\Rdf\Vocabulary;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

/**
 * The W3C RDF Dataset Canonicalization (RDFC-1.0) test suite, vendored
 * under tests/fixtures/rdf-canon: evaluation tests compare the canonical
 * N-Quads, map tests the issued identifiers, the negative test expects the
 * poison-graph limit to stop the run.
 */
#[Test]
#[Covers(Canonicalizer::class)]
#[Covers(CanonicalizationRun::class)]
#[Covers(IdentifierIssuer::class)]
#[Covers(Dataset::class)]
#[Covers(Quad::class)]
#[Covers(Literal::class)]
#[Covers(BlankNode::class)]
#[Covers(Iri::class)]
#[Covers(Vocabulary::class)]
#[Covers(NQuads::class)]
#[Covers(NQuadsWriter::class)]
#[Covers(CanonicalForm::class)]
final class W3cRdfCanonTest
{
    private const string FIXTURES = __DIR__ . '/fixtures/rdf-canon/';

    #[DataProvider('evaluationProvider')]
    public function evaluation(string $input, string $expected, HashAlgorithm $algorithm): void
    {
        $form = (new Canonicalizer(hashAlgorithm: $algorithm))->canonicalize(JsonLd::parseNQuads($input));

        Assert::same($form->nquads, $expected);
    }

    #[DataProvider('mapProvider')]
    public function map(string $input, array $expected, HashAlgorithm $algorithm): void
    {
        $form = (new Canonicalizer(hashAlgorithm: $algorithm))->canonicalize(JsonLd::parseNQuads($input));
        $issued = [];

        foreach ($form->issuedIdentifiers as $label => $canonical) {
            $issued[substr($label, 2)] = substr($canonical, 2);
        }

        ksort($issued, SORT_STRING);
        ksort($expected, SORT_STRING);

        Assert::same($issued, $expected);
    }

    #[DataProvider('negativeProvider')]
    public function poisonGraphIsRefused(string $input): void
    {
        $code = null;

        try {
            (new Canonicalizer())->canonicalize(JsonLd::parseNQuads($input));
        } catch (CanonicalizationLimitException $exception) {
            $code = $exception->errorCode();
        }

        Assert::same($code, ErrorCode::CanonicalizationLimitExceeded);
    }

    public static function evaluationProvider(): iterable
    {
        foreach (self::entries('rdfc:RDFC10EvalTest') as $name => $entry) {
            yield $name => [self::read($entry['action']), self::read($entry['result']), $entry['algorithm']];
        }
    }

    public static function mapProvider(): iterable
    {
        foreach (self::entries('rdfc:RDFC10MapTest') as $name => $entry) {
            yield $name => [self::read($entry['action']), Json::decode(self::read($entry['result'])), $entry['algorithm']];
        }
    }

    public static function negativeProvider(): iterable
    {
        foreach (self::entries('rdfc:RDFC10NegativeEvalTest') as $name => $entry) {
            yield $name => [self::read($entry['action'])];
        }
    }

    /**
     * @return iterable<string, array{action: string, result: string, algorithm: HashAlgorithm}>
     */
    private static function entries(string $type): iterable
    {
        $manifest = Json::decode(self::read('manifest.jsonld'));

        foreach ($manifest['entries'] as $entry) {
            if ($entry['type'] !== $type) {
                continue;
            }

            yield $entry['id'] . ' ' . $entry['name'] => [
                'action' => (string) $entry['action'],
                'result' => (string) ($entry['result'] ?? ''),
                'algorithm' => ($entry['hashAlgorithm'] ?? null) === 'SHA384' ? HashAlgorithm::Sha384 : HashAlgorithm::Sha256,
            ];
        }
    }

    private static function read(string $path): string
    {
        return (string) file_get_contents(self::FIXTURES . $path);
    }
}
