<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Exception\JsonLdException;
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
    public function case(mixed $input, mixed $expected, ?string $expectedError, array $option): void
    {
        $dataset = JsonLd::parseNQuads((string) $input);

        $fromRdf = static fn(): array => JsonLd::fromRdf(
            $dataset,
            useNativeTypes: ($option['useNativeTypes'] ?? false) === true,
            useRdfType: ($option['useRdfType'] ?? false) === true,
            rdfDirection: $option['rdfDirection'] ?? null,
        );

        if ($expectedError !== null) {
            $message = null;

            try {
                $fromRdf();
            } catch (JsonLdException $exception) {
                $message = $exception->getMessage();
            }

            Assert::same($message, $expectedError);

            return;
        }

        Assert::same(W3cExpandTest::canonical($fromRdf()), W3cExpandTest::canonical($expected));
    }

    public static function suiteProvider(): iterable
    {
        foreach ((new W3cManifest())->cases('fromRdf') as $name => $case) {
            yield $name => [
                $case['input'],
                $case['expected'],
                $case['expectedError'],
                $case['option'],
            ];
        }
    }
}
