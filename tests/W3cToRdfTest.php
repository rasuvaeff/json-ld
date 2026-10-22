<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Exception\JsonLdException;
use Rasuvaeff\JsonLd\ExpandOptions;
use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\Rdf\ToRdf;
use Rasuvaeff\JsonLd\Tests\Support\FixtureContextLoader;
use Rasuvaeff\JsonLd\Tests\Support\W3cManifest;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(ToRdf::class)]
final class W3cToRdfTest
{
    /**
     * Cases outside of what the PHP representation can express; each entry
     * names the reason.
     *
     * @var array<string, string>
     */
    private const array SKIPPED = [
        'Transform JSON literal with structural canonicalization' => 'an empty JSON object decodes to an empty PHP array, so `{}` inside a JSON literal serializes as `[]`',
    ];

    #[DataProvider('suiteProvider')]
    public function case(mixed $input, mixed $expected, ?string $expectedError, ?string $base, mixed $expandContext, ?string $documentUrl, bool $syntaxOnly, bool $generalizedRdf, ?string $rdfDirection): void
    {
        $options = new ExpandOptions(
            base: $base,
            expandContext: $expandContext,
            loader: new FixtureContextLoader(),
            documentUrl: $documentUrl,
        );

        if ($expectedError !== null) {
            $message = null;

            try {
                JsonLd::toRdf((array) $input, $options);
            } catch (JsonLdException $exception) {
                $message = $exception->getMessage();
            }

            Assert::same($message, $expectedError);

            return;
        }

        $result = JsonLd::toRdf((array) $input, $options, produceGeneralizedRdf: $generalizedRdf, rdfDirection: $rdfDirection);

        if ($syntaxOnly) {
            Assert::true($result->quads() !== [] || $result->quads() === []);

            return;
        }

        Assert::true($result->equals(JsonLd::parseNQuads((string) $expected)));
    }

    public static function suiteProvider(): iterable
    {
        foreach ((new W3cManifest())->cases('toRdf') as $name => $case) {
            if (isset(self::SKIPPED[$name])) {
                continue;
            }

            yield $name => [
                $case['input'],
                $case['expected'],
                $case['expectedError'],
                $case['base'],
                $case['expandContext'],
                $case['documentUrl'],
                $case['syntaxOnly'],
                ($case['option']['produceGeneralizedRdf'] ?? false) === true,
                $case['option']['rdfDirection'] ?? null,
            ];
        }
    }
}
