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
    #[DataProvider('suiteProvider')]
    public function case(mixed $input, mixed $expected, ?string $expectedError, ?string $base, mixed $expandContext, array $option): void
    {
        if ($expectedError !== null) {
            Assert::same(null, $expectedError);

            return;
        }

        $dataset = JsonLd::parseNQuads((string) $input);

        $useNativeTypes = ($option['useNativeTypes'] ?? false) === true;

        $result = JsonLd::fromRdf($dataset, useNativeTypes: $useNativeTypes);

        Assert::same(W3cExpandTest::canonical($result), W3cExpandTest::canonical($expected));
    }

    public static function suiteProvider(): iterable
    {
        foreach ((new W3cManifest())->cases('fromRdf') as $name => $case) {
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
