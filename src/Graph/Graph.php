<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Graph;

/**
 * A JSON-LD graph: an immutable set of nodes built from node objects in
 * flattened form, where every node has an `@id` and refers to other nodes
 * with `{"@id": …}` references.
 *
 * @api
 */
final readonly class Graph
{
    /** @var array<string, Node> */
    private array $byId;

    /** @var list<Node> */
    private array $nodes;

    /**
     * @param array<array-key, array<array-key, mixed>> $nodes node objects in flattened form, for example one graph of
     *                                             {@see \Rasuvaeff\JsonLd\JsonLd::flatten()}
     */
    public function __construct(array $nodes)
    {
        $list = [];
        $byId = [];

        foreach ($nodes as $entry) {
            if (array_is_list($entry)) {
                continue;
            }

            $node = new Node($entry, $this);
            $list[] = $node;

            $id = $node->id();

            if ($id !== null) {
                $byId[$id] = $node;
            }
        }

        $this->nodes = $list;
        $this->byId = $byId;
    }

    /**
     * @return list<Node> every node of the graph, referenced-only ones (just an `@id`) included
     */
    public function nodes(): array
    {
        return $this->nodes;
    }

    public function node(string $id): ?Node
    {
        return $this->byId[$id] ?? null;
    }

    /**
     * @return list<Node>
     */
    public function byType(string $iri): array
    {
        $result = [];

        foreach ($this->nodes as $node) {
            if ($node->hasType($iri)) {
                $result[] = $node;
            }
        }

        return $result;
    }
}
