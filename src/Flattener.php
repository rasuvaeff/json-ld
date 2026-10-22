<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd;

/**
 * Flattening, JSON-LD 1.1 API section 5.2.3: merges every node object of a
 * document into one list of top-level nodes (the suite's flattened form).
 *
 * @api
 */
final class Flattener
{
    public function __construct(private readonly Expander $expander = new Expander()) {}

    /**
     * Flattens a document given as decoded JSON or an inline JSON string.
     *
     * @param string|array<mixed> $input
     *
     * @return list<mixed> every node of the document, embedded nodes
     *                    replaced by references, sorted by subject
     *
     * @throws Exception\JsonLdException with a W3C error code as the message
     */
    public function flatten(string|array $input, ?ExpandOptions $options = null): array
    {
        $expanded = $this->expander->expand($input, $options);

        /** @var array<string, array<string, array<string, mixed>>> $graphs graph name => subject => node */
        $graphs = [];

        $generator = new BlankNodeGenerator();

        foreach ($expanded as $node) {
            $this->generateNodeMap($node, $graphs, '@default', $generator);
        }

        $graph = $graphs['@default'] ?? [];

        $nodes = array_values($graph);

        usort(
            $nodes,
            static fn(array $left, array $right): int => self::compareSubjects(
                (string) ($left[Keywords::ID] ?? ''),
                (string) ($right[Keywords::ID] ?? ''),
            ),
        );

        return $nodes;
    }

    /**
     * Node Map generation, JSON-LD 1.1 API section 5.2.4. Returns the
     * subject the element was registered under.
     *
     * @param mixed $element expanded node object
     * @param array<string, array<string, array<string, mixed>>> $graphs
     *
     * @return string subject identifier of the registered node
     */
    private function generateNodeMap(
        mixed $element,
        array &$graphs,
        string $activeGraph,
        BlankNodeGenerator $generator,
    ): string {
        if (!is_array($element) || array_is_list($element)) {
            // Value entries have no identity of their own.
            return '';
        }

        $subject = is_string($element[Keywords::ID] ?? null)
            ? $element[Keywords::ID]
            : $generator->next();

        if (!isset($graphs[$activeGraph][$subject])) {
            $graphs[$activeGraph][$subject] = [Keywords::ID => $subject];
        }

        /** @var array<string, mixed> $node */
        $node = &$graphs[$activeGraph][$subject];

        foreach ($element as $property => $value) {
            if ($property === Keywords::ID) {
                continue;
            }

            if ($property === Keywords::TYPE) {
                $this->addUnique($node, Keywords::TYPE, $value);

                continue;
            }

            if ($property === Keywords::INDEX || $property === Keywords::VALUE
                || $property === Keywords::LANGUAGE || $property === Keywords::DIRECTION
            ) {
                $node[$property] = $value;

                continue;
            }

            if ($property === Keywords::REVERSE) {
                foreach ($value as $reverseProperty => $reverseValues) {
                    foreach ($reverseValues as $reverseNode) {
                        $reverseId = $this->generateNodeMap($reverseNode, $graphs, $activeGraph, $generator);
                        $reverseEntry = &$graphs[$activeGraph][$reverseId];
                        $this->addUnique($reverseEntry, $reverseProperty, [Keywords::ID => $subject]);
                        unset($reverseEntry);
                    }
                }

                continue;
            }

            if ($property === Keywords::GRAPH) {
                $inner = is_array($value) && array_is_list($value) ? $value : [$value];

                foreach ($inner as $graphNode) {
                    $this->generateNodeMap($graphNode, $graphs, $subject, $generator);
                }

                // The @graph member itself is kept on the node.
                $this->addUnique($node, Keywords::GRAPH, $inner, true);

                continue;
            }

            if ($property === Keywords::INCLUDED) {
                $inner = is_array($value) && array_is_list($value) ? $value : [$value];

                foreach ($inner as $includedNode) {
                    $this->generateNodeMap($includedNode, $graphs, $activeGraph, $generator);
                }

                $this->addUnique($node, Keywords::INCLUDED, $inner, true);

                continue;
            }

            $values = is_array($value) && array_is_list($value) ? $value : [$value];

            foreach ($values as $entry) {
                $this->addPropertyValue($node, $property, $entry, $graphs, $activeGraph, $generator);
            }
        }

        unset($node);

        return $subject;
    }

    /**
     * Adds one expanded property entry to a node-map node: value objects
     * are copied, list objects keep their shape (with embedded nodes
     * registered and referenced), node objects are registered and
     * referenced by identifier.
     *
     * @param array<string, mixed> $node
     * @param mixed $entry
     * @param array<string, array<string, array<string, mixed>>> $graphs
     */
    private function addPropertyValue(
        array &$node,
        string $property,
        mixed $entry,
        array &$graphs,
        string $activeGraph,
        BlankNodeGenerator $generator,
    ): void {
        if (!is_array($entry) || array_is_list($entry)) {
            $this->addUnique($node, $property, $entry, true);

            return;
        }

        if (array_key_exists(Keywords::LIST, $entry)) {
            $items = is_array($entry[Keywords::LIST]) && array_is_list($entry[Keywords::LIST])
                ? $entry[Keywords::LIST]
                : [$entry[Keywords::LIST]];

            $listItems = [];

            foreach ($items as $item) {
                $listItems[] = $this->listItem($item, $graphs, $activeGraph, $generator);
            }

            $this->addUnique($node, $property, [Keywords::LIST => $listItems], true);

            return;
        }

        if (array_key_exists(Keywords::VALUE, $entry)) {
            $this->addUnique($node, $property, $entry, true);

            return;
        }

        $id = $this->generateNodeMap($entry, $graphs, $activeGraph, $generator);

        $this->addUnique($node, $property, [Keywords::ID => $id], true);
    }

    /**
     * One item of an `@list`: value objects pass through, node objects
     * become references (after registration).
     *
     * @param mixed $item
     * @param array<string, array<string, array<string, mixed>>> $graphs
     *
     * @return mixed
     */
    private function listItem(
        mixed $item,
        array &$graphs,
        string $activeGraph,
        BlankNodeGenerator $generator,
    ): mixed {
        if (!is_array($item) || array_is_list($item) || array_key_exists(Keywords::VALUE, $item)
            || array_key_exists(Keywords::LIST, $item)
        ) {
            return $item;
        }

        $id = $this->generateNodeMap($item, $graphs, $activeGraph, $generator);

        return [Keywords::ID => $id];
    }

    /**
     * @param array<string, mixed> $node
     * @param mixed $value
     */
    private function addUnique(array &$node, string $property, mixed $value, bool $asArray = false): void
    {
        if ($property === Keywords::TYPE) {
            $types = is_array($value) && array_is_list($value) ? $value : [$value];

            foreach ($types as $type) {
                if (!in_array($type, $node[Keywords::TYPE] ?? [], true)) {
                    $node[Keywords::TYPE][] = $type;
                }
            }

            return;
        }

        if (!array_key_exists($property, $node)) {
            $node[$property] = $asArray
                ? (is_array($value) && array_is_list($value) ? $value : [$value])
                : $value;

            return;
        }

        $existing = $node[$property];

        if ($asArray && (!is_array($existing) || !array_is_list($existing))) {
            $existing = [$existing];
            $node[$property] = $existing;
        }

        $values = is_array($value) && array_is_list($value) ? $value : [$value];

        foreach ($values as $item) {
            if (!in_array($item, $existing, true)) {
                $existing[] = $item;
            }
        }

        $node[$property] = $existing;
    }

    private static function compareSubjects(string $left, string $right): int
    {
        $leftBlank = str_starts_with($left, '_:');
        $rightBlank = str_starts_with($right, '_:');

        if ($leftBlank !== $rightBlank) {
            return $leftBlank ? 1 : -1;
        }

        return strcmp($left, $right);
    }
}
