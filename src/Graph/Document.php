<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Graph;

use Rasuvaeff\JsonLd\Keywords;

/**
 * A JSON-LD document in expanded form: the default graph plus the named
 * graphs found in `@graph` members.
 *
 * @api
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
        $default = [];
        $named = [];

        foreach ($expanded as $entry) {
            if (!is_array($entry) || array_is_list($entry)) {
                continue;
            }

            $graphEntries = $entry[Keywords::GRAPH] ?? null;
            $id = $entry[Keywords::ID] ?? null;

            if ($graphEntries === null) {
                $default[] = $entry;

                continue;
            }

            $graphName = is_string($id) ? $id : self::DEFAULT_GRAPH;
            $items = is_array($graphEntries) && array_is_list($graphEntries) ? $graphEntries : [$graphEntries];

            foreach ($items as $item) {
                if (is_array($item) && !array_is_list($item) && !array_key_exists(Keywords::GRAPH, $item)) {
                    $named[$graphName][] = $item;
                }
            }
        }

        $this->graphs = [self::DEFAULT_GRAPH => new Graph($default)];

        foreach ($named as $name => $items) {
            $this->graphs[$name] = new Graph($items);
        }
    }

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
