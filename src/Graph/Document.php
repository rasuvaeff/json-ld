<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Graph;

use Rasuvaeff\JsonLd\Exception\JsonLdException;
use Rasuvaeff\JsonLd\Keywords;
use Rasuvaeff\JsonLd\NodeMap;

/**
 * A JSON-LD document as a set of graphs: the default graph plus one graph
 * per `@graph` with a name.
 *
 * The graphs come from the node map of the expanded document (JSON-LD 1.1
 * API section 7.2), the same structure flattening uses: every node object
 * of the document, embedded ones included, is a node of its graph, and
 * embedded objects become references. Nodes without `@id` (an embedded
 * schema.org `author`, say) get blank node labels `_:b0`, `_:b1`, … in the
 * order the algorithm meets them; labels from the input are relabeled the
 * same way. The labels are stable for the same input but are not the
 * labels the input used. IRIs are kept as they are.
 *
 * @api
 */
final readonly class Document
{
    public const string DEFAULT_GRAPH = Keywords::DEFAULT_GRAPH;

    /**
     * @param array<string, Graph> $graphs
     */
    private function __construct(private array $graphs) {}

    /**
     * Builds the document from the expanded form, for example the output of
     * {@see \Rasuvaeff\JsonLd\JsonLd::expand()}.
     *
     * @param list<mixed> $expanded
     *
     * @throws JsonLdException `conflicting indexes` when one node carries two different `@index` values
     */
    public static function fromExpanded(array $expanded): self
    {
        $graphs = [];

        foreach (NodeMap::of($expanded)->graphs as $name => $nodes) {
            $graphs[strval($name)] = new Graph($nodes);
        }

        return new self($graphs);
    }

    public function defaultGraph(): Graph
    {
        return $this->graphs[self::DEFAULT_GRAPH];
    }

    public function graph(string $name): ?Graph
    {
        return $this->graphs[$name] ?? null;
    }

    /**
     * @return array<string, Graph> graph name => graph; the default graph is {@see self::DEFAULT_GRAPH}
     */
    public function graphs(): array
    {
        return $this->graphs;
    }
}
