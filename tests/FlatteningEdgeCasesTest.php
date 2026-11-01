<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Context\ActiveContext;
use Rasuvaeff\JsonLd\Context\ContextProcessor;
use Rasuvaeff\JsonLd\Context\DefinitionScope;
use Rasuvaeff\JsonLd\Context\Syntax;
use Rasuvaeff\JsonLd\Context\TermDefinition;
use Rasuvaeff\JsonLd\DocumentExpansion;
use Rasuvaeff\JsonLd\Expander;
use Rasuvaeff\JsonLd\ExpansionRun;
use Rasuvaeff\JsonLd\Flattener;
use Rasuvaeff\JsonLd\Iri;
use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\Keywords;
use Rasuvaeff\JsonLd\Loader\InlineContextLoader;
use Rasuvaeff\JsonLd\Loader\LoadedContext;
use Rasuvaeff\JsonLd\Loader\MapContextLoader;
use Rasuvaeff\JsonLd\NodeMap;
use Rasuvaeff\JsonLd\ProcessingOptions;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(ContextProcessor::class)]
#[Covers(NodeMap::class)]
#[Covers(Flattener::class)]
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
final class FlatteningEdgeCasesTest
{
    /**
     * @param array<array-key, mixed> $document
     * @param list<array<string, mixed>> $expected
     */
    #[DataProvider('documentProvider')]
    public function flattensDocuments(array $document, array $expected): void
    {
        Assert::same(JsonLd::flatten($document), $expected);
    }

    public static function documentProvider(): iterable
    {
        yield 'named graphs are sorted by name' => [
            [
                ['@id' => 'http://g/2', '@graph' => [['@id' => 'http://x/a', 'http://x/p' => 'v']]],
                ['@id' => 'http://g/1', '@graph' => [['@id' => 'http://x/b', 'http://x/p' => 'w']]],
            ],
            [
                ['@id' => 'http://g/1', '@graph' => [['@id' => 'http://x/b', 'http://x/p' => [['@value' => 'w']]]]],
                ['@id' => 'http://g/2', '@graph' => [['@id' => 'http://x/a', 'http://x/p' => [['@value' => 'v']]]]],
            ],
        ];
        yield 'blank nodes are labelled in property order' => [
            ['http://x/b' => ['http://x/c' => '1'], 'http://x/a' => ['http://x/c' => '2']],
            [
                ['@id' => '_:b0', 'http://x/a' => [['@id' => '_:b1']], 'http://x/b' => [['@id' => '_:b2']]],
                ['@id' => '_:b1', 'http://x/c' => [['@value' => '2']]],
                ['@id' => '_:b2', 'http://x/c' => [['@value' => '1']]],
            ],
        ];
        yield 'blank node types are relabelled' => [
            ['@id' => 'http://x/n', '@type' => ['_:t', 'http://x/T'], 'http://x/p' => ['@id' => '_:t']],
            [['@id' => 'http://x/n', '@type' => ['_:b0', 'http://x/T'], 'http://x/p' => [['@id' => '_:b0']]]],
        ];
        yield 'lists keep their order' => [
            ['@id' => 'http://x/n', 'http://x/p' => ['@list' => [['@id' => 'http://x/a'], 'v']]],
            [['@id' => 'http://x/n', 'http://x/p' => [['@list' => [['@id' => 'http://x/a'], ['@value' => 'v']]]]]],
        ];
        yield 'reverse properties become forward ones' => [
            ['@id' => 'http://x/n', '@reverse' => ['http://x/r' => ['@id' => 'http://x/m']]],
            [['@id' => 'http://x/m', 'http://x/r' => [['@id' => 'http://x/n']]]],
        ];
        yield 'duplicate references collapse' => [
            ['@id' => 'http://x/n', 'http://x/p' => [['@id' => 'http://x/o'], ['@id' => 'http://x/o']]],
            [['@id' => 'http://x/n', 'http://x/p' => [['@id' => 'http://x/o']]]],
        ];
        yield 'duplicate values collapse' => [
            ['@id' => 'http://x/n', 'http://x/p' => [['@value' => 'a'], ['@value' => 'a']]],
            [['@id' => 'http://x/n', 'http://x/p' => [['@value' => 'a']]]],
        ];
        yield 'values differing only by index stay apart' => [
            ['@id' => 'http://x/n', 'http://x/p' => [['@value' => 'a', '@index' => 'i'], ['@value' => 'a', '@index' => 'j']]],
            [['@id' => 'http://x/n', 'http://x/p' => [['@value' => 'a', '@index' => 'i'], ['@value' => 'a', '@index' => 'j']]]],
        ];
    }
}
