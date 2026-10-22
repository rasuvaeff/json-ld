<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Graph;

/**
 * A JSON-LD graph: an immutable set of nodes built from expanded node
 * objects.
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
     * @param list<array<string, mixed>> $expanded list of expanded node objects
     */
    public function __construct(array $expanded)
    {
        $nodes = [];
        $byId = [];

        foreach ($expanded as $entry) {
            if (!is_array($entry) || array_is_list($entry)) {
                continue;
            }

            $node = new Node($entry, $this);
            $nodes[] = $node;

            $id = $node->id();

            if ($id !== null) {
                $byId[$id] = $node;
            }
        }

        $this->nodes = $nodes;
        $this->byId = $byId;
    }

    /**
     * @return list<Node>
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
