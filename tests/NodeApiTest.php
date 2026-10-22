<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Graph\Document;
use Rasuvaeff\JsonLd\JsonLd;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(Document::class)]
final class NodeApiTest
{
    public function readsNodesValuesAndReferences(): void
    {
        $document = JsonLd::document([
            '@context' => ['@vocab' => 'http://s/'],
            '@graph' => [
                ['@id' => 'http://e/a', '@type' => 'Person', 'name' => 'Alice', 'knows' => ['@id' => 'http://e/b']],
                ['@id' => 'http://e/b', 'name' => 'Bob'],
            ],
        ]);

        $graph = $document->defaultGraph();
        $alice = $graph->node('http://e/a');

        Assert::same(count($graph->nodes()), 2);
        Assert::same($alice?->value('http://s/name'), 'Alice');
        Assert::same($alice?->types(), ['http://s/Person']);
        Assert::same($alice?->reference('http://s/knows')?->value('http://s/name'), 'Bob');
        Assert::same(count($graph->byType('http://s/Person')), 1);
    }

    public function exposesNamedGraphs(): void
    {
        $document = JsonLd::document([
            '@id' => 'http://e/g',
            '@graph' => [['@id' => 'http://e/a', 'http://s/name' => 'Alice']],
        ]);

        Assert::same($document->graph('http://e/g')?->node('http://e/a')?->value('http://s/name'), 'Alice');
    }
}
