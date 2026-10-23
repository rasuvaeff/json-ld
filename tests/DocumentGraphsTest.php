<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Flattener;
use Rasuvaeff\JsonLd\Graph\Document;
use Rasuvaeff\JsonLd\JsonLd;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(Document::class)]
#[Covers(Flattener::class)]
final class DocumentGraphsTest
{
    public function skipsEntriesThatAreNotNodeObjects(): void
    {
        $document = new Document([
            'scalar',
            [1, 2],
            ['@id' => 'http://a', 'http://p' => [['@value' => 'x']]],
        ]);

        Assert::same(count($document->defaultGraph()->nodes()), 1);
        Assert::same(count($document->graphs()), 1);
    }

    public function acceptsASingleNodeObjectAsTheGraphContent(): void
    {
        $document = new Document([
            ['@id' => 'http://g', '@graph' => ['@id' => 'http://a', 'http://p' => [['@value' => 'x']]]],
        ]);

        Assert::same(count($document->graph('http://g')?->nodes() ?? []), 1);
        Assert::same($document->graph('http://g')?->node('http://a')?->value('http://p'), 'x');
    }

    public function keepsOnlyNodeObjectsWithoutNestedGraphsInsideAGraph(): void
    {
        $document = new Document([
            ['@id' => 'http://g', '@graph' => [
                'scalar',
                [1, 2],
                ['@id' => 'http://nested', '@graph' => [['@id' => 'http://deep']]],
                ['@id' => 'http://a', 'http://p' => [['@value' => 'x']]],
            ]],
        ]);

        $nodes = $document->graph('http://g')?->nodes() ?? [];

        Assert::same(count($nodes), 1);
        Assert::same($document->graph('http://g')?->node('http://a')?->value('http://p'), 'x');
    }

    public function flattensGraphsAndNodesInSortedOrder(): void
    {
        $flat = JsonLd::flatten([
            ['@id' => 'http://z', 'http://p' => 'z'],
            ['@id' => 'http://a', 'http://p' => 'a'],
            ['@id' => 'http://g/z', '@graph' => [
                ['@id' => 'http://s2', 'http://p' => '2'],
                ['@id' => 'http://s1', 'http://p' => '1'],
            ]],
            ['@id' => 'http://g/a', '@graph' => [['@id' => 'http://s', 'http://p' => '0']]],
        ]);

        Assert::same(
            array_map(static fn(array $node): mixed => $node['@id'], $flat),
            ['http://a', 'http://g/a', 'http://g/z', 'http://z'],
        );

        $graph = $flat[2]['@graph'] ?? null;

        Assert::same(
            is_array($graph) ? array_map(static fn(mixed $node): mixed => is_array($node) ? $node['@id'] : null, $graph) : null,
            ['http://s1', 'http://s2'],
        );
    }
}
