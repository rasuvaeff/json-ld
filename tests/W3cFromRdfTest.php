<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\Rdf\FromRdf;
use Rasuvaeff\JsonLd\Tests\Support\W3cManifest;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(FromRdf::class)]
final class W3cFromRdfTest
{
    /**
     * Features not implemented yet; entries disappear as they land.
     *
     * @var list<string>
     */
    private const array SKIPPED = ['i18n-datatype', 'compound literal', 'compound-literal'];

    #[DataProvider('suiteProvider')]
    public function case(mixed $input, mixed $expected, ?string $expectedError, ?string $base, mixed $expandContext, array $option): void
    {
        if ($expectedError !== null) {
            Assert::same(null, $expectedError);

            return;
        }

        $dataset = JsonLd::parseNQuads((string) $input);

        $useNativeTypes = ($option['useNativeTypes'] ?? false) === true;
        $useRdfType = ($option['useRdfType'] ?? false) === true;

        $result = (new FromRdf(useNativeTypes: $useNativeTypes, useRdfType: $useRdfType))->fromRdf($dataset);

        Assert::same(W3cExpandTest::canonical($result), W3cExpandTest::canonical($expected));
    }

    public static function suiteProvider(): iterable
    {
        foreach ((new W3cManifest())->cases('fromRdf') as $name => $case) {
            foreach (self::SKIPPED as $skip) {
                if (str_contains($name, $skip)) {
                    continue 2;
                }
            }

            yield $name => [
                $case['input'],
                $case['expected'],
                $case['expectedError'],
                $case['base'],
                $case['expandContext'],
                $case['option'],
            ];
        }
    }
}
