<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\ExpandOptions;
use Rasuvaeff\JsonLd\Exception\JsonLdException;
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
    #[DataProvider('suiteProvider')]
    public function case(mixed $input, mixed $expected, ?string $expectedError, ?string $base, mixed $expandContext, bool $syntaxOnly): void
    {
        $options = new ExpandOptions(
            base: $base,
            expandContext: $expandContext,
            loader: new FixtureContextLoader(),
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

        $result = JsonLd::serializeNQuads(JsonLd::toRdf((array) $input, $options));

        if ($syntaxOnly) {
            Assert::true(is_string($result));

            return;
        }

        Assert::same(self::normalized($result), self::normalized((string) $expected));
    }

    public static function suiteProvider(): iterable
    {
        foreach ((new W3cManifest())->cases('toRdf') as $name => $case) {
            yield $name => [
                $case['input'],
                $case['expected'],
                $case['expectedError'],
                $case['base'],
                $case['expandContext'],
                $case['syntaxOnly'],
            ];
        }
    }

    /**
     * N-Quads comparison as required by the suite: lines unordered, blank
     * node labels replaced with canonical first-occurrence names.
     *
     * @return list<string>
     */
    public static function normalized(string $nquads): array
    {
        $lines = [];

        foreach (preg_split('~\r\n|\r|\n~', trim($nquads), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $line) {
            $line = trim($line);

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        sort($lines);

        // Sequential renaming of blank node labels by first occurrence in
        // the sorted order gives both sides a shared labeling scheme.
        $mapping = [];

        $renamed = array_map(
            static function (string $line) use (&$mapping): string {
                return preg_replace_callback(
                    '~_:([A-Za-z0-9_\\-.]+)~',
                    static function (array $m) use (&$mapping): string {
                        $label = $m[1];

                        if (!isset($mapping[$label])) {
                            $mapping[$label] = 'c' . count($mapping);
                        }

                        return '_:' . $mapping[$label];
                    },
                    $line,
                ) ?? $line;
            },
            $lines,
        );

        sort($renamed);

        return $renamed;
    }
}
