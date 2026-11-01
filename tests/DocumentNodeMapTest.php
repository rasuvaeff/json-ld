<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Graph\Document;
use Rasuvaeff\JsonLd\Graph\Graph;
use Rasuvaeff\JsonLd\Graph\Node;
use Rasuvaeff\JsonLd\Graph\Value;
use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\Loader\MapContextLoader;
use Rasuvaeff\JsonLd\ProcessingOptions;
use Rasuvaeff\JsonLd\Tests\Support\Strict;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

/**
 * The node API is built from the node map: embedded nodes are nodes of
 * their graph, the ones without `@id` get blank node labels.
 */
#[Test]
#[Covers(Document::class)]
#[Covers(Graph::class)]
#[Covers(Node::class)]
final class DocumentNodeMapTest
{
    private const string SCHEMA = 'https://schema.org/';

    public function embeddedNodesWithoutIdAreReachable(): void
    {
        $document = JsonLd::document($this->article(), $this->options());
        $article = $document->defaultGraph()->node('https://example.com/articles/1');
        $author = $article?->reference(self::SCHEMA . 'author');

        Assert::same($author?->value(self::SCHEMA . 'name'), 'Alice');
        Assert::same($author?->types(), [self::SCHEMA . 'Person']);
        Assert::same($author?->id(), '_:b0');
        Assert::same($article?->reference(self::SCHEMA . 'video')?->value(self::SCHEMA . 'name'), 'Intro');
        Assert::same(count($document->defaultGraph()->byType(self::SCHEMA . 'Person')), 1);
    }

    public function embeddedObjectsBecomeReferences(): void
    {
        $article = JsonLd::document($this->article(), $this->options())->defaultGraph()->node('https://example.com/articles/1');

        Assert::same($article?->properties()[self::SCHEMA . 'author'] ?? null, [['@id' => '_:b0']]);
        Assert::same($article?->toArray()['@id'] ?? null, 'https://example.com/articles/1');
    }

    public function nodesKeepTheirIrisAndComeInDocumentOrder(): void
    {
        $nodes = JsonLd::document($this->article(), $this->options())->defaultGraph()->nodes();

        Assert::same(
            array_map(static fn(Node $node): ?string => $node->id(), $nodes),
            ['https://example.com/articles/1', '_:b0', 'https://example.com/videos/1'],
        );
    }

    public function blankNodeLabelsAreStableAndInputLabelsAreReplaced(): void
    {
        $input = [
            '@context' => ['@vocab' => 'http://s/'],
            '@graph' => [
                ['@id' => '_:alice', 'knows' => ['@id' => '_:bob'], 'pet' => ['name' => 'Rex']],
                ['@id' => '_:bob', 'knows' => ['@id' => '_:alice']],
            ],
        ];

        $ids = static fn(): array => array_map(
            static fn(Node $node): ?string => $node->id(),
            JsonLd::document($input)->defaultGraph()->nodes(),
        );

        Assert::same($ids(), ['_:b0', '_:b1', '_:b2']);
        Assert::same($ids(), $ids());

        $alice = JsonLd::document($input)->defaultGraph()->node('_:b0');

        Assert::same($alice?->reference('http://s/knows')?->reference('http://s/knows')?->id(), '_:b0');
        Assert::same($alice?->reference('http://s/pet')?->value('http://s/name'), 'Rex');
    }

    public function aBareReferenceResolvesToANodeWithoutProperties(): void
    {
        $node = JsonLd::document(['@id' => 'http://e/a', 'http://s/p' => ['@id' => 'http://e/b']])
            ->defaultGraph()
            ->node('http://e/a')
            ?->reference('http://s/p');

        Assert::same($node?->id(), 'http://e/b');
        Assert::same($node?->properties(), []);
    }

    public function reverseProperties(): void
    {
        $graph = JsonLd::document([
            '@id' => 'http://e/parent',
            '@reverse' => ['http://s/parent' => ['@id' => 'http://e/child', 'http://s/name' => 'Kid']],
        ])->defaultGraph();

        Assert::same($graph->node('http://e/child')?->reference('http://s/parent')?->id(), 'http://e/parent');
    }

    public function valueObjectsCarryLanguageTypeAndDirection(): void
    {
        $node = JsonLd::document([
            '@context' => ['@vocab' => 'http://s/', '@version' => 1.1],
            '@id' => 'http://e/a',
            'name' => [
                ['@value' => 'Moscow', '@language' => 'en'],
                ['@value' => 'Москва', '@language' => 'ru'],
                ['@value' => 'موسكو', '@language' => 'ar', '@direction' => 'rtl'],
                'plain',
                ['@value' => '2026-11-01', '@type' => 'http://www.w3.org/2001/XMLSchema#date'],
                ['@value' => 42],
                ['@id' => 'http://e/b'],
                ['@value' => ['a' => 1], '@type' => '@json'],
                ['@value' => true, '@type' => '@json'],
            ],
        ])->defaultGraph()->node('http://e/a');

        Assert::same(
            array_map(static fn(Value $value): array => [$value->value, $value->language, $value->type, $value->direction], $node?->valueObjects('http://s/name') ?? []),
            [
                ['Moscow', 'en', null, null],
                ['Москва', 'ru', null, null],
                ['موسكو', 'ar', null, 'rtl'],
                ['plain', null, null, null],
                ['2026-11-01', null, 'http://www.w3.org/2001/XMLSchema#date', null],
                [42, null, null, null],
                [true, null, '@json', null],
            ],
        );
        Assert::same($node?->valueObjects('http://s/missing'), []);
    }

    public function accessorsReturnEveryMatch(): void
    {
        $graph = JsonLd::document([
            '@context' => ['@vocab' => 'http://s/'],
            '@graph' => [
                [
                    '@id' => 'http://e/a',
                    '@type' => ['Person', 'Agent'],
                    '@index' => 'first',
                    'name' => ['Alice', 'Al'],
                    'knows' => [['@id' => 'http://e/b'], ['@id' => 'http://e/c'], ['@id' => 'http://e/unknown-in-graph']],
                ],
                ['@id' => 'http://e/b', '@type' => 'Person', 'name' => 'Bob'],
                ['@id' => 'http://e/c', 'name' => 'Carol'],
            ],
        ])->defaultGraph();

        $alice = $graph->node('http://e/a');

        Strict::run(static function () use ($graph, $alice): void {
            Assert::same($alice?->types(), ['http://s/Person', 'http://s/Agent']);
            Assert::same($alice?->values('http://s/name'), ['Alice', 'Al']);
            Assert::same($alice?->values('@index'), ['first']);
            Assert::same($alice?->values('@type'), ['http://s/Person', 'http://s/Agent']);
            Assert::same($alice?->valueObjects('@type'), []);
            Assert::same($alice?->references('@type'), []);
            Assert::same($alice?->references('http://s/name'), []);
            Assert::same($alice?->valueObjects('http://s/knows'), []);
            Assert::same(
                array_map(static fn(Node $node): ?string => $node->id(), $alice?->references('http://s/knows') ?? []),
                ['http://e/b', 'http://e/c', 'http://e/unknown-in-graph'],
            );
            Assert::same(array_keys($alice?->properties() ?? []), ['@index', 'http://s/knows', 'http://s/name']);
            Assert::same($alice?->properties()['@index'] ?? null, ['first']);
            Assert::same(array_keys($alice?->toArray() ?? []), ['@id', '@type', '@index', 'http://s/knows', 'http://s/name']);
            Assert::same(
                array_map(static fn(Node $node): ?string => $node->id(), $graph->byType('http://s/Person')),
                ['http://e/a', 'http://e/b'],
            );
        });
    }

    public function graphNodesCanBeBuiltFromFlattenedForm(): void
    {
        $graph = new Graph([
            ['@id' => 'http://e/a', 'http://s/knows' => [['@id' => 'http://e/b']]],
            [1, 2],
            ['@id' => 'http://e/b', 'http://s/name' => [['@value' => 'Bob']]],
        ]);

        Assert::same(count($graph->nodes()), 2);
        Assert::same($graph->node('http://e/a')?->reference('http://s/knows')?->value('http://s/name'), 'Bob');
    }

    /**
     * @return array<string, mixed>
     */
    private function article(): array
    {
        return require __DIR__ . '/../examples/article.php';
    }

    private function options(): ProcessingOptions
    {
        return new ProcessingOptions(loader: new MapContextLoader([
            self::SCHEMA => ['@context' => ['@vocab' => self::SCHEMA]],
        ]));
    }
}
