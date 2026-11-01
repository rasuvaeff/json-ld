<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Exception\JsonLdException;
use Rasuvaeff\JsonLd\Flattener;
use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\ProcessingOptions;
use Rasuvaeff\JsonLd\Tests\Support\FixtureContextLoader;
use Rasuvaeff\JsonLd\Tests\Support\W3cManifest;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(Flattener::class)]
final class W3cFlattenTest
{
    /**
     * Cases that need the compaction algorithm (a `context` is applied to
     * the flattened result).
     *
     * @var array<string, string>
     */
    private const array SKIPPED = [
        'compactArrays option' => 'requires JSON-LD compaction, which this package does not implement',
    ];

    #[DataProvider('suiteProvider')]
    public function case(mixed $input, mixed $expected, ?string $expectedError, ?string $base, mixed $expandContext, ?string $documentUrl): void
    {
        $options = new ProcessingOptions(
            base: $base,
            documentUrl: $documentUrl,
            expandContext: $expandContext,
            loader: new FixtureContextLoader(),
        );

        if ($expectedError !== null) {
            $message = null;

            try {
                JsonLd::flatten((array) $input, $options);
            } catch (JsonLdException $exception) {
                $message = $exception->errorCode()->value;
            }

            Assert::same($message, $expectedError);

            return;
        }

        $result = JsonLd::flatten((array) $input, $options);

        Assert::same(W3cExpandTest::canonical($result), W3cExpandTest::canonical($expected));
    }

    public static function suiteProvider(): iterable
    {
        foreach ((new W3cManifest())->cases('flatten') as $name => $case) {
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
                $case['option'],
            ];
        }
    }
}
