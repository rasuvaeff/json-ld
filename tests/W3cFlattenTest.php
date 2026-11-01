<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Context\ActiveContext;
use Rasuvaeff\JsonLd\Context\ContextProcessor;
use Rasuvaeff\JsonLd\Context\DefinitionScope;
use Rasuvaeff\JsonLd\Context\Syntax;
use Rasuvaeff\JsonLd\Context\TermDefinition;
use Rasuvaeff\JsonLd\DocumentExpansion;
use Rasuvaeff\JsonLd\Exception\JsonLdException;
use Rasuvaeff\JsonLd\Expander;
use Rasuvaeff\JsonLd\ExpansionRun;
use Rasuvaeff\JsonLd\Flattener;
use Rasuvaeff\JsonLd\Iri;
use Rasuvaeff\JsonLd\Json;
use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\Keywords;
use Rasuvaeff\JsonLd\Loader\InlineContextLoader;
use Rasuvaeff\JsonLd\Loader\LoadedContext;
use Rasuvaeff\JsonLd\Loader\MapContextLoader;
use Rasuvaeff\JsonLd\NodeMap;
use Rasuvaeff\JsonLd\ProcessingOptions;
use Rasuvaeff\JsonLd\Tests\Support\FixtureContextLoader;
use Rasuvaeff\JsonLd\Tests\Support\W3cManifest;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(Flattener::class)]
#[Covers(ExpansionRun::class)]
#[Covers(JsonLd::class)]
#[Covers(Json::class)]
#[Covers(Keywords::class)]
#[Covers(Iri::class)]
#[Covers(ProcessingOptions::class)]
#[Covers(ContextProcessor::class)]
#[Covers(ActiveContext::class)]
#[Covers(Syntax::class)]
#[Covers(TermDefinition::class)]
#[Covers(DefinitionScope::class)]
#[Covers(InlineContextLoader::class)]
#[Covers(MapContextLoader::class)]
#[Covers(LoadedContext::class)]
#[Covers(NodeMap::class)]
#[Covers(DocumentExpansion::class)]
#[Covers(Expander::class)]
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
