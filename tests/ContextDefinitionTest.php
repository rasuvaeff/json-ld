<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Context\ActiveContext;
use Rasuvaeff\JsonLd\Context\ContextProcessor;
use Rasuvaeff\JsonLd\Context\DefinitionScope;
use Rasuvaeff\JsonLd\Context\Syntax;
use Rasuvaeff\JsonLd\Context\TermDefinition;
use Rasuvaeff\JsonLd\DocumentExpansion;
use Rasuvaeff\JsonLd\Exception\DocumentLoadingException;
use Rasuvaeff\JsonLd\Exception\ErrorCode;
use Rasuvaeff\JsonLd\Exception\JsonLdException;
use Rasuvaeff\JsonLd\Expander;
use Rasuvaeff\JsonLd\ExpansionRun;
use Rasuvaeff\JsonLd\Iri;
use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\Keywords;
use Rasuvaeff\JsonLd\Loader\ContextLoaderInterface;
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
final class ContextDefinitionTest
{
    /**
     * @param array<string, mixed> $context
     */
    #[DataProvider('invalidContextProvider')]
    public function rejectsInvalidContexts(array $context, ErrorCode $expected): void
    {
        Assert::same($this->errorOf(['@context' => $context, 'a' => 'v']), $expected);
    }

    public static function invalidContextProvider(): iterable
    {
        yield 'version as a string' => [['@version' => '1.1'], ErrorCode::InvalidVersionValue];
        yield 'version 1.0' => [['@version' => 1.0], ErrorCode::InvalidVersionValue];
        yield 'version 1' => [['@version' => 1], ErrorCode::InvalidVersionValue];
        yield 'version true' => [['@version' => true], ErrorCode::InvalidVersionValue];
        yield 'import not a string' => [['@import' => 5], ErrorCode::InvalidImportValue];
        yield 'import of a document without context' => [['@import' => 'https://ctx/no-context'], ErrorCode::InvalidRemoteContext];
        yield 'import of a list document' => [['@import' => 'https://ctx/list'], ErrorCode::InvalidRemoteContext];
        yield 'import of a document with a list context' => [['@import' => 'https://ctx/list-context'], ErrorCode::InvalidRemoteContext];
        yield 'vocab relative' => [['@vocab' => 'relative'], ErrorCode::InvalidVocabMapping];
        yield 'vocab keyword-like' => [['@vocab' => '@foo'], ErrorCode::InvalidVocabMapping];
        yield 'protected not a boolean' => [['@protected' => 'yes'], ErrorCode::InvalidProtectedValue];
        yield 'propagate not a boolean' => [['@propagate' => 'no'], ErrorCode::InvalidPropagateValue];
        yield 'base direction' => [['@direction' => 'up'], ErrorCode::InvalidBaseDirection];
        yield 'type redefined as a string' => [['@type' => 'x'], ErrorCode::KeywordRedefinition];
        yield 'type redefined with another container' => [['@type' => ['@container' => '@list']], ErrorCode::KeywordRedefinition];
        yield 'type redefined with an id' => [['@type' => ['@id' => 'http://x/t']], ErrorCode::KeywordRedefinition];
        yield 'type redefined with a set container and an id' => [['@type' => ['@container' => '@set', '@id' => 'http://x/t']], ErrorCode::KeywordRedefinition];
        yield 'keyword redefined' => [['@id' => 'http://x/id'], ErrorCode::KeywordRedefinition];
        yield 'cyclic mapping' => [['a' => 'b', 'b' => 'a'], ErrorCode::CyclicIriMapping];
        yield 'self referencing prefix' => [['a' => 'a:x'], ErrorCode::CyclicIriMapping];
        yield 'term definition is a number' => [['a' => 5], ErrorCode::InvalidTermDefinition];
        yield 'term definition is a list' => [['a' => [1]], ErrorCode::InvalidTermDefinition];
        yield 'term protected not a boolean' => [['a' => ['@id' => 'http://x/a', '@protected' => 'yes']], ErrorCode::InvalidProtectedValue];
        yield 'term type not a string' => [['a' => ['@id' => 'http://x/a', '@type' => 5]], ErrorCode::InvalidTypeMapping];
        yield 'term type relative' => [['a' => ['@id' => 'http://x/a', '@type' => 'foo']], ErrorCode::InvalidTypeMapping];
        yield 'term type keyword' => [['a' => ['@id' => 'http://x/a', '@type' => '@list']], ErrorCode::InvalidTypeMapping];
        yield 'term type keyword-like' => [['a' => ['@id' => 'http://x/a', '@type' => '@foo']], ErrorCode::InvalidTypeMapping];
        yield 'term type blank node' => [['a' => ['@id' => 'http://x/a', '@type' => '_:b']], ErrorCode::InvalidTypeMapping];
        yield 'term id not a string' => [['a' => ['@id' => 5]], ErrorCode::InvalidIriMapping];
        yield 'term id relative' => [['a' => ['@id' => 'relative']], ErrorCode::InvalidIriMapping];
        yield 'term id context keyword' => [['a' => ['@id' => '@context']], ErrorCode::InvalidKeywordAlias];
        yield 'term with a slash that is relative' => [['x/y' => ['@type' => '@id']], ErrorCode::InvalidIriMapping];
        yield 'term reverse not a string' => [['a' => ['@reverse' => 5]], ErrorCode::InvalidIriMapping];
        yield 'term reverse relative' => [['a' => ['@reverse' => 'relative']], ErrorCode::InvalidIriMapping];
        yield 'term reverse with id' => [['a' => ['@reverse' => 'http://x/r', '@id' => 'http://x/a']], ErrorCode::InvalidReverseProperty];
        yield 'term reverse with nest' => [['a' => ['@reverse' => 'http://x/r', '@nest' => '@nest']], ErrorCode::InvalidReverseProperty];
        yield 'term reverse with a list container' => [['a' => ['@reverse' => 'http://x/r', '@container' => '@list']], ErrorCode::InvalidReverseProperty];
        yield 'term container as a map' => [['a' => ['@id' => 'http://x/a', '@container' => ['k' => '@set']]], ErrorCode::InvalidContainerMapping];
        yield 'term container item not a string' => [['a' => ['@id' => 'http://x/a', '@container' => [5]]], ErrorCode::InvalidContainerMapping];
        yield 'term container unknown keyword' => [['a' => ['@id' => 'http://x/a', '@container' => '@foo']], ErrorCode::InvalidContainerMapping];
        yield 'term container duplicates' => [['a' => ['@id' => 'http://x/a', '@container' => ['@set', '@set']]], ErrorCode::InvalidContainerMapping];
        yield 'term container impossible pair' => [['a' => ['@id' => 'http://x/a', '@container' => ['@list', '@set']]], ErrorCode::InvalidContainerMapping];
        yield 'term index without index container' => [['a' => ['@id' => 'http://x/a', '@container' => '@set', '@index' => 'http://x/i']], ErrorCode::InvalidTermDefinition];
        yield 'term index not a string' => [['a' => ['@id' => 'http://x/a', '@container' => '@index', '@index' => 5]], ErrorCode::InvalidTermDefinition];
        yield 'term index relative' => [['a' => ['@id' => 'http://x/a', '@container' => '@index', '@index' => 'rel']], ErrorCode::InvalidTermDefinition];
        yield 'term index keyword' => [['a' => ['@id' => 'http://x/a', '@container' => '@index', '@index' => '@type']], ErrorCode::InvalidTermDefinition];
        yield 'term direction' => [['a' => ['@id' => 'http://x/a', '@direction' => 'up']], ErrorCode::InvalidBaseDirection];
        yield 'term nest not a string' => [['a' => ['@id' => 'http://x/a', '@nest' => 5]], ErrorCode::InvalidNestValue];
        yield 'term nest keyword' => [['a' => ['@id' => 'http://x/a', '@nest' => '@id']], ErrorCode::InvalidNestValue];
        yield 'term prefix not a boolean' => [['a' => ['@id' => 'http://x/a', '@prefix' => 'yes']], ErrorCode::InvalidPrefixValue];
        yield 'term prefix on a compact iri term' => [['p' => 'http://p/', 'p:q' => ['@id' => 'http://p/q', '@prefix' => true]], ErrorCode::InvalidTermDefinition];
        yield 'term prefix on a term with a slash' => [['@vocab' => 'http://x/', 'x/y' => ['@prefix' => true]], ErrorCode::InvalidTermDefinition];
        yield 'term prefix on a keyword alias' => [['a' => ['@id' => '@id', '@prefix' => true]], ErrorCode::InvalidTermDefinition];
        yield 'term type that cycles back to the term' => [['a' => ['@id' => 'http://x/a', '@type' => 'a']], ErrorCode::InvalidTypeMapping];
        yield 'term index that cycles back to the term' => [['a' => ['@id' => 'http://x/a', '@container' => '@index', '@index' => 'a']], ErrorCode::InvalidTermDefinition];
        yield 'term index keyword-like' => [['a' => ['@id' => 'http://x/a', '@container' => '@index', '@index' => '@foo']], ErrorCode::InvalidTermDefinition];
        yield 'term with a newline around the colon is a compact iri term' => [["x\n:y\n" => 'http://p/y'], ErrorCode::InvalidIriMapping];
        yield 'term with a slash that disagrees with its id' => [['@vocab' => 'http://v/', 'x/y' => ['@id' => 'http://other/y']], ErrorCode::InvalidIriMapping];
        yield 'term with an unknown member' => [['a' => ['@id' => 'http://x/a', '@foo' => 1]], ErrorCode::InvalidTermDefinition];
    }

    /**
     * @param array<string, mixed> $context
     * @param list<array<string, mixed>> $expected
     */
    #[DataProvider('validContextProvider')]
    public function acceptsValidContexts(array $context, mixed $value, array $expected): void
    {
        Assert::same($this->expand(['@context' => $context, 'a' => $value]), $expected);
    }

    public static function validContextProvider(): iterable
    {
        $plain = [['http://x/a' => [['@value' => 'v']]]];

        yield 'version 1.1' => [['@version' => 1.1, 'a' => 'http://x/a'], 'v', $plain];
        yield 'protected flag' => [['@protected' => true, 'a' => 'http://x/a'], 'v', $plain];
        yield 'type with a set container' => [['@type' => ['@container' => '@set'], 'a' => 'http://x/a'], 'v', $plain];
        yield 'type with a set container, protected' => [['@type' => ['@container' => '@set', '@protected' => true], 'a' => 'http://x/a'], 'v', $plain];
        yield 'type protected only' => [['@type' => ['@protected' => true], 'a' => 'http://x/a'], 'v', $plain];
        yield 'empty term definition under a vocab' => [['@vocab' => 'http://x/', 'a' => []], 'v', $plain];
        yield 'null container' => [['a' => ['@id' => 'http://x/a', '@container' => null]], 'v', $plain];
        yield 'container keywords are order independent' => [['a' => ['@id' => 'http://x/a', '@container' => ['@type', '@set']]], 'v', [['http://x/a' => [['@id' => 'v']]]]];
        yield 'container keywords in the other order' => [['a' => ['@id' => 'http://x/a', '@container' => ['@set', '@type']]], 'v', [['http://x/a' => [['@id' => 'v']]]]];
        yield 'index container with an index iri' => [['a' => ['@id' => 'http://x/a', '@container' => '@index', '@index' => 'http://x/i']], 'v', $plain];
        yield 'index container with a blank node index' => [['a' => ['@id' => 'http://x/a', '@container' => '@index', '@index' => '_:i']], 'v', $plain];
        yield 'nest to the nest keyword' => [['a' => ['@id' => 'http://x/a', '@nest' => '@nest']], 'v', $plain];
        yield 'nest to a term' => [['a' => ['@id' => 'http://x/a', '@nest' => 'n']], 'v', $plain];
        yield 'type mapping to a keyword' => [['a' => ['@id' => 'http://x/a', '@type' => '@none']], 'v', [['http://x/a' => [['@value' => 'v']]]]];
        yield 'reverse with a set container' => [['a' => ['@reverse' => 'http://x/r', '@container' => '@set']], ['@id' => 'http://x/n'], [['@reverse' => ['http://x/r' => [['@id' => 'http://x/n']]]]]];
        yield 'reverse with a null container' => [['a' => ['@reverse' => 'http://x/r', '@container' => null]], ['@id' => 'http://x/n'], [['@reverse' => ['http://x/r' => [['@id' => 'http://x/n']]]]]];
        yield 'reverse with an index container' => [['a' => ['@reverse' => 'http://x/r', '@container' => '@index']], ['k' => ['@id' => 'http://x/n']], [['@reverse' => ['http://x/r' => [['@id' => 'http://x/n', '@index' => 'k']]]]]];
        yield 'rtl direction' => [['a' => ['@id' => 'http://x/a', '@direction' => 'rtl']], 'v', [['http://x/a' => [['@value' => 'v', '@direction' => 'rtl']]]]];
        yield 'ltr direction' => [['a' => ['@id' => 'http://x/a', '@direction' => 'ltr']], 'v', [['http://x/a' => [['@value' => 'v', '@direction' => 'ltr']]]]];
        yield 'null direction' => [['@direction' => 'rtl', 'a' => ['@id' => 'http://x/a', '@direction' => null]], 'v', $plain];
        yield 'base direction' => [['@direction' => 'rtl', 'a' => 'http://x/a'], 'v', [['http://x/a' => [['@value' => 'v', '@direction' => 'rtl']]]]];
        yield 'dependent terms in any order' => [['a' => 'p:x', 'p' => 'http://x/'], 'v', [['http://x/x' => [['@value' => 'v']]]]];
        yield 'dependent term through its own colon' => [['p' => 'http://x/', 'a' => ['@id' => 'p:a']], 'v', $plain];
        yield 'prefix term with a colon in its name' => [['p' => 'http://x/', 'a:b' => ['@type' => '@id']], 'v', []];
        yield 'expanded term is not a prefix' => [['p' => ['@id' => 'http://x/'], 'a' => 'p:a'], 'v', [['p:a' => [['@value' => 'v']]]]];
        yield 'expanded term marked as a prefix' => [['p' => ['@id' => 'http://x/', '@prefix' => true], 'a' => 'p:a'], 'v', $plain];
        yield 'slash term under a vocab' => [['@vocab' => 'http://x/', 'a' => ['@type' => '@id']], 'v', [['http://x/a' => [['@id' => 'v']]]]];
        yield 'keyword-like id is ignored' => [['a' => '@foo', '@vocab' => 'http://x/'], 'v', $plain];
        yield 'null context nullifies then later items apply' => [[null, ['a' => 'http://x/a']], 'v', $plain];
        yield 'slash term agreeing with its id' => [['@vocab' => 'http://v/', 'x/y' => ['@id' => 'http://v/x/y'], 'a' => 'http://x/a'], 'v', $plain];
        yield 'keyword alias that is not a prefix' => [['a' => ['@id' => '@id', '@prefix' => false]], 'v', []];
        yield 'term referenced before its keyword-like id' => [['b' => 'a:x', 'a' => '@foo'], 'v', []];
        yield 'term referenced before its keyword-like reverse' => [['b' => 'a:x', 'a' => ['@reverse' => '@foo']], 'v', []];
        yield 'keyword-like term is ignored' => [['@foo' => 'http://x/foo', 'a' => 'http://x/a'], 'v', $plain];
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $document
     * @param list<array<string, mixed>> $expected
     */
    #[DataProvider('compactTermProvider')]
    public function expandsCompactIriTerms(array $context, array $document, array $expected): void
    {
        Assert::same($this->expand(['@context' => $context, ...$document]), $expected);
    }

    public static function compactTermProvider(): iterable
    {
        yield 'term named like a compact iri' => [
            ['p' => 'http://x/', 'p:a' => ['@type' => '@id']],
            ['p:a' => 'v'],
            [['http://x/a' => [['@id' => 'v']]]],
        ];
        yield 'prefix defined after the term that uses it' => [
            ['p:a' => ['@type' => '@id'], 'p' => 'http://x/'],
            ['p:a' => 'v'],
            [['http://x/a' => [['@id' => 'v']]]],
        ];
        yield 'prefix mapped to null' => [
            ['p' => null, 'p:a' => ['@type' => '@id']],
            ['p:a' => 'v'],
            [['p:a' => [['@id' => 'v']]]],
        ];
        yield 'key using a null prefix' => [
            ['p' => null],
            ['p:a' => 'v'],
            [['p:a' => [['@value' => 'v']]]],
        ];
        yield 'key using a prefix that is not a prefix' => [
            ['p' => ['@id' => 'http://x/']],
            ['p:a' => 'v'],
            [['p:a' => [['@value' => 'v']]]],
        ];
        yield 'key using a prefix' => [
            ['p' => 'http://x/'],
            ['p:a' => 'v'],
            [['http://x/a' => [['@value' => 'v']]]],
        ];
        yield 'blank node prefix' => [
            ['_' => 'http://x/'],
            ['_:a' => 'v'],
            [['_:a' => [['@value' => 'v']]]],
        ];
        yield 'double slash is never a prefix' => [
            ['http' => 'http://y/'],
            ['http://x/a' => 'v'],
            [['http://x/a' => [['@value' => 'v']]]],
        ];
        yield 'propagate false limits the context to the node' => [
            ['@propagate' => false, 'a' => 'http://x/a', 'c' => 'http://x/c'],
            ['a' => 'v', 'c' => ['a' => 'w']],
            [['http://x/a' => [['@value' => 'v']], 'http://x/c' => [[]]]],
        ];
        yield 'propagate true keeps the context for children' => [
            ['@propagate' => true, 'a' => 'http://x/a', 'c' => 'http://x/c'],
            ['a' => 'v', 'c' => ['a' => 'w']],
            [['http://x/a' => [['@value' => 'v']], 'http://x/c' => [['http://x/a' => [['@value' => 'w']]]]]],
        ];
    }

    public function baseOfARemoteContextIsItsFinalLocation(): void
    {
        $loader = new class implements ContextLoaderInterface {
            #[\Override]
            public function load(string $reference): LoadedContext
            {
                return match ($reference) {
                    'https://orig.example/ctx' => new LoadedContext('https://final.example/dir/ctx', ['@context' => ['@import' => 'inner']]),
                    'https://final.example/dir/inner' => new LoadedContext('https://final.example/dir/inner', ['@context' => ['a' => 'http://x/a']]),
                    default => throw new DocumentLoadingException(ErrorCode::LoadingDocumentFailed, $reference),
                };
            }
        };

        Assert::same(
            JsonLd::expand(['@context' => 'https://orig.example/ctx', 'a' => 'v'], new ProcessingOptions(loader: $loader)),
            [['http://x/a' => [['@value' => 'v']]]],
        );
    }

    public function followsRemoteContexts(): void
    {
        $documents = ['https://ctx/one' => ['@context' => ['a' => 'http://x/a']]];

        Assert::same(
            $this->expand(['@context' => 'https://ctx/one', 'a' => 'v'], $documents),
            [['http://x/a' => [['@value' => 'v']]]],
        );
    }

    public function followsNestedRemoteContextsUpToSixtyFour(): void
    {
        Assert::same(
            $this->expand(['@context' => 'https://ctx/0', 'a' => 'v'], $this->chain(63)),
            [['http://x/a' => [['@value' => 'v']]]],
        );
        Assert::same($this->errorOf(['@context' => 'https://ctx/0', 'a' => 'v'], $this->chain(64)), ErrorCode::ContextOverflow);
    }

    public function refusesRemoteContextCycles(): void
    {
        $documents = [
            'https://ctx/a' => ['@context' => 'https://ctx/b'],
            'https://ctx/b' => ['@context' => 'https://ctx/a'],
        ];

        Assert::same($this->errorOf(['@context' => 'https://ctx/a', 'a' => 'v'], $documents), ErrorCode::RecursiveContextInclusion);
    }

    public function refusesRemoteDocumentsWithoutAContext(): void
    {
        Assert::same(
            $this->errorOf(['@context' => 'https://ctx/no-context', 'a' => 'v']),
            ErrorCode::InvalidRemoteContext,
        );
        Assert::same(
            $this->errorOf(['@context' => 'https://ctx/list', 'a' => 'v']),
            ErrorCode::InvalidRemoteContext,
        );
    }

    public function importsMergeUnderTheImportingContext(): void
    {
        $documents = ['https://ctx/base' => ['@context' => ['a' => 'http://x/imported', 'b' => 'http://x/b']]];

        Assert::same(
            $this->expand(['@context' => ['@import' => 'https://ctx/base', 'a' => 'http://x/a'], 'a' => 'v', 'b' => 'w'], $documents),
            [['http://x/a' => [['@value' => 'v']], 'http://x/b' => [['@value' => 'w']]]],
        );
    }

    /**
     * @param array<string, mixed> $document
     * @param array<string, array<string|int, mixed>>|null $documents
     *
     * @return list<array<string, mixed>>
     */
    private function expand(array $document, ?array $documents = null): array
    {
        /** @var list<array<string, mixed>> */
        return JsonLd::expand($document, new ProcessingOptions(loader: new MapContextLoader($documents ?? $this->documents())));
    }

    /**
     * @param array<string, mixed> $document
     * @param array<string, array<string|int, mixed>>|null $documents
     */
    private function errorOf(array $document, ?array $documents = null): ?ErrorCode
    {
        try {
            $this->expand($document, $documents);
        } catch (JsonLdException $exception) {
            return $exception->errorCode();
        }

        return null;
    }

    /**
     * @return array<string, array<string|int, mixed>>
     */
    private function documents(): array
    {
        return [
            'https://ctx/no-context' => ['a' => 1],
            'https://ctx/list' => [1, 2],
            'https://ctx/list-context' => ['@context' => [['a' => 'http://x/a']]],
        ];
    }

    /**
     * @param int<1, max> $length number of chained contexts, the last one defines the term
     *
     * @return array<string, array<string|int, mixed>>
     */
    private function chain(int $length): array
    {
        $documents = [];

        for ($i = 0; $i < $length; ++$i) {
            $documents['https://ctx/' . $i] = ['@context' => 'https://ctx/' . ($i + 1)];
        }

        $documents['https://ctx/' . $length] = ['@context' => ['a' => 'http://x/a']];

        return $documents;
    }
}
