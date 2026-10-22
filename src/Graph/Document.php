<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Graph;

use Rasuvaeff\JsonLd\Keywords;

/**
 * A JSON-LD document in expanded form: the default graph plus the named
 * graphs found in `@graph` members.
 *
 * @api
 *
 * @psalm-import-type JsonValue from \Rasuvaeff\JsonLd\Json
 */
final readonly class Document
{
    public const string DEFAULT_GRAPH = '@default';

    /** @var array<string, Graph> */
    private array $graphs;

    /**
     * @param list<mixed> $expanded output of the expander
     */
    public function __construct(array $expanded)
    {
        /** @var list<array<array-key, mixed>> $default */
        $default = [];
        /** @var array<string, list<array<array-key, mixed>>> $named */
        $named = [];

        foreach ($expanded as $entry) {
            if (!is_array($entry) || array_is_list($entry)) {
                continue;
            }

            /** @var JsonValue $graphEntries */
            $graphEntries = $entry[Keywords::GRAPH] ?? null;
            /** @var JsonValue $id */
            $id = $entry[Keywords::ID] ?? null;

            if ($graphEntries === null) {
                $default[] = $entry;

                continue;
            }

            $graphName = is_string($id) ? $id : self::DEFAULT_GRAPH;
            $items = is_array($graphEntries) && array_is_list($graphEntries) ? $graphEntries : [$graphEntries];

            /** @var JsonValue $item */
            foreach ($items as $item) {
                if (is_array($item) && !array_is_list($item) && !array_key_exists(Keywords::GRAPH, $item)) {
                    $named[$graphName][] = $item;
                }
            }
        }

        $graphs = [self::DEFAULT_GRAPH => new Graph($default)];

        foreach ($named as $name => $items) {
            $graphs[$name] = new Graph($items);
        }

        $this->graphs = $graphs;
    }

    /**
     * @param list<mixed> $expanded
     */
    public static function fromExpanded(array $expanded): self
    {
        return new self($expanded);
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
     * @return array<string, Graph>
     */
    public function graphs(): array
    {
        return $this->graphs;
    }
}
