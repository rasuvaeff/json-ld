<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Context\ActiveContext;
use Rasuvaeff\JsonLd\Context\ContextProcessor;
use Rasuvaeff\JsonLd\Context\DefinitionScope;
use Rasuvaeff\JsonLd\Context\Syntax;
use Rasuvaeff\JsonLd\Context\TermDefinition;
use Rasuvaeff\JsonLd\DocumentExpansion;
use Rasuvaeff\JsonLd\Exception\ErrorCode;
use Rasuvaeff\JsonLd\Exception\JsonLdException;
use Rasuvaeff\JsonLd\Expander;
use Rasuvaeff\JsonLd\ExpansionRun;
use Rasuvaeff\JsonLd\Iri;
use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\Keywords;
use Rasuvaeff\JsonLd\Loader\InlineContextLoader;
use Rasuvaeff\JsonLd\Loader\LoadedContext;
use Rasuvaeff\JsonLd\Loader\MapContextLoader;
use Rasuvaeff\JsonLd\ProcessingOptions;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(ContextProcessor::class)]
#[Covers(ActiveContext::class)]
#[Covers(Syntax::class)]
#[Covers(TermDefinition::class)]
#[Covers(DefinitionScope::class)]
#[Covers(ExpansionRun::class)]
#[Covers(DocumentExpansion::class)]
#[Covers(Expander::class)]
#[Covers(JsonLd::class)]
#[Covers(Keywords::class)]
#[Covers(Iri::class)]
#[Covers(ProcessingOptions::class)]
#[Covers(InlineContextLoader::class)]
#[Covers(MapContextLoader::class)]
#[Covers(LoadedContext::class)]
final class ExpansionEdgeCasesTest
{
    /**
     * @param array<string, mixed> $document
     * @param list<array<string, mixed>> $expected
     */
    #[DataProvider('documentProvider')]
    public function expandsDocuments(array $document, array $expected): void
    {
        Assert::same(JsonLd::expand($document), $expected);
    }

    public static function documentProvider(): iterable
    {
        $scoped = static fn(string $name, string $term): array => [
            '@id' => 'http://x/' . $name,
            '@context' => ['v' => 'http://x/' . $term],
        ];

        yield 'type-scoped contexts apply in term order' => [
            ['@context' => ['t1' => $scoped('t1', 'first'), 't2' => $scoped('t2', 'second')], '@type' => ['t2', 't1'], 'v' => 'w'],
            [['@type' => ['http://x/t2', 'http://x/t1'], 'http://x/second' => [['@value' => 'w']]]],
        ];
        yield 'type-scoped contexts apply in key order of the type aliases' => [
            ['@context' => ['ty1' => '@type', 'ty2' => '@type', 'tA' => $scoped('tA', 'first'), 'tB' => $scoped('tB', 'second')], 'ty2' => 'tB', 'ty1' => 'tA', 'v' => 'w'],
            [['@type' => ['http://x/tB', 'http://x/tA'], 'http://x/second' => [['@value' => 'w']]]],
        ];
        yield 'numeric keys are plain strings' => [
            ['@context' => ['a' => 'http://x/a'], '1' => 'v', 'a' => 'w'],
            [['http://x/a' => [['@value' => 'w']]]],
        ];
        yield 'keyword-like keys are dropped' => [
            ['@foo' => 'v', 'http://x/a' => 'w'],
            [['http://x/a' => [['@value' => 'w']]]],
        ];
        yield 'property-scoped contexts may redefine protected terms' => [
            ['@context' => ['@protected' => true, 't' => 'http://x/t', 'a' => ['@id' => 'http://x/a', '@context' => ['t' => 'http://x/t2']]], 'a' => ['t' => 'v']],
            [['http://x/a' => [['http://x/t2' => [['@value' => 'v']]]]]],
        ];
        yield 'rtl direction' => [
            ['http://x/a' => ['@value' => 'v', '@direction' => 'rtl']],
            [['http://x/a' => [['@value' => 'v', '@direction' => 'rtl']]]],
        ];
        yield 'ltr direction' => [
            ['http://x/a' => ['@value' => 'v', '@direction' => 'ltr']],
            [['http://x/a' => [['@value' => 'v', '@direction' => 'ltr']]]],
        ];
        yield 'index of a value object stays on the value object' => [
            ['http://x/a' => ['@value' => 'v', '@index' => 'k']],
            [['http://x/a' => [['@value' => 'v', '@index' => 'k']]]],
        ];
        yield 'language map skips null entries' => [
            ['@context' => ['a' => ['@id' => 'http://x/a', '@container' => '@language']], 'a' => ['en' => [null, 'x'], 'de' => 'y']],
            [['http://x/a' => [['@value' => 'x', '@language' => 'en'], ['@value' => 'y', '@language' => 'de']]]],
        ];
        yield 'graph container' => [
            ['@context' => ['a' => ['@id' => 'http://x/a', '@container' => '@graph']], 'a' => ['http://x/b' => 'v']],
            [['http://x/a' => [['@graph' => [['http://x/b' => [['@value' => 'v']]]]]]]],
        ];
        yield 'index container' => [
            ['@context' => ['a' => ['@id' => 'http://x/a', '@container' => '@index']], 'a' => ['k' => 'v', 'j' => ['@id' => 'http://x/o']]],
            [['http://x/a' => [['@value' => 'v', '@index' => 'k'], ['@id' => 'http://x/o', '@index' => 'j']]]],
        ];
        yield 'graph index container' => [
            ['@context' => ['a' => ['@id' => 'http://x/a', '@container' => ['@graph', '@index']]], 'a' => ['k' => ['http://x/b' => 'v']]],
            [['http://x/a' => [['@graph' => [['http://x/b' => [['@value' => 'v']]]], '@index' => 'k']]]],
        ];
        yield 'double reverse maps to forward properties' => [
            ['@context' => ['r1' => ['@reverse' => 'http://x/p'], 'r2' => ['@reverse' => 'http://x/p2']], '@id' => 'http://x/n', '@reverse' => ['r1' => ['@id' => 'http://x/o'], 'r2' => ['@id' => 'http://x/o2']]],
            [['@id' => 'http://x/n', 'http://x/p' => [['@id' => 'http://x/o']], 'http://x/p2' => [['@id' => 'http://x/o2']]]],
        ];
    }

    /**
     * @param array<string, mixed> $document
     */
    #[DataProvider('invalidDocumentProvider')]
    public function rejectsInvalidDocuments(array $document, ErrorCode $expected): void
    {
        $actual = null;

        try {
            JsonLd::expand($document);
        } catch (JsonLdException $exception) {
            $actual = $exception->errorCode();
        }

        Assert::same($actual, $expected);
    }

    public static function invalidDocumentProvider(): iterable
    {
        yield 'type number' => [['@type' => 5], ErrorCode::InvalidTypeValue];
        yield 'type list of numbers' => [['@type' => [5]], ErrorCode::InvalidTypeValue];
        yield 'type null' => [['@type' => null], ErrorCode::InvalidTypeValue];
        yield 'type keyword-like' => [['@type' => '@foo'], ErrorCode::InvalidTypeValue];
        yield 'type map' => [['@type' => ['k' => 'v']], ErrorCode::InvalidTypeValue];
        yield 'direction' => [['http://x/a' => ['@value' => 'v', '@direction' => 'up']], ErrorCode::InvalidBaseDirection];
        yield 'index not a string' => [['http://x/a' => ['@value' => 'v', '@index' => 5]], ErrorCode::InvalidIndexValue];
        yield 'empty reverse' => [['@id' => 'http://x/n', '@reverse' => []], ErrorCode::InvalidReverseValue];
        yield 'reverse value object' => [['@id' => 'http://x/n', '@reverse' => ['http://x/r' => ['@value' => 'v']]], ErrorCode::InvalidReversePropertyValue];
        yield 'reverse list object' => [['@id' => 'http://x/n', '@reverse' => ['http://x/r' => ['@list' => []]]], ErrorCode::InvalidReversePropertyValue];
    }
}
