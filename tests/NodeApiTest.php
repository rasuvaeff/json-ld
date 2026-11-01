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
use Rasuvaeff\JsonLd\Graph\Document;
use Rasuvaeff\JsonLd\Graph\Graph;
use Rasuvaeff\JsonLd\Graph\Node;
use Rasuvaeff\JsonLd\Graph\Value;
use Rasuvaeff\JsonLd\Iri;
use Rasuvaeff\JsonLd\Json;
use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\Keywords;
use Rasuvaeff\JsonLd\Loader\InlineContextLoader;
use Rasuvaeff\JsonLd\Loader\LoadedContext;
use Rasuvaeff\JsonLd\Loader\MapContextLoader;
use Rasuvaeff\JsonLd\NodeMap;
use Rasuvaeff\JsonLd\ProcessingOptions;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(Document::class)]
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
#[Covers(DocumentExpansion::class)]
#[Covers(Expander::class)]
#[Covers(Graph::class)]
#[Covers(Node::class)]
#[Covers(Value::class)]
#[Covers(NodeMap::class)]
#[Covers(Flattener::class)]
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
