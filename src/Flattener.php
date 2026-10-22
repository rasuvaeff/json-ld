<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd;

/**
 * Flattening, JSON-LD 1.1 API section 5.3: merges every node object of a
 * document into one list of top-level nodes; named graphs become `@graph`
 * entries of their graph node.
 *
 * @api
 */
final readonly class Flattener
{
    public function __construct(private Expander $expander = new Expander()) {}

    /**
     * Flattens a document given as decoded JSON or an inline JSON string.
     *
     * @param string|array<mixed> $input
     *
     * @return list<array<string, mixed>> every node of the document, embedded nodes
     *                    replaced by references, sorted by subject
     *
     * @throws Exception\JsonLdException with a W3C error code as the message
     */
    public function flatten(string|array $input, ?ExpandOptions $options = null): array
    {
        $map = NodeMap::of($this->expander->expandRaw($input, $options));
        $graphs = $map->graphs;

        $default = $graphs[Keywords::DEFAULT_GRAPH];
        unset($graphs[Keywords::DEFAULT_GRAPH]);
        ksort($graphs, SORT_STRING);

        foreach ($graphs as $name => $graph) {
            $name = strval($name);
            $default[$name] ??= [Keywords::ID => $name];
            ksort($graph, SORT_STRING);
            $entries = [];

            foreach ($graph as $node) {
                if (count($node) > 1) {
                    $entries[] = $node;
                }
            }

            $default[$name][Keywords::GRAPH] = $entries;
        }

        ksort($default, SORT_STRING);

        return array_values(array_filter($default, static fn(array $node): bool => count($node) > 1));
    }
}
