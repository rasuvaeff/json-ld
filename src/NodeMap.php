<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd;

use Rasuvaeff\JsonLd\Exception\InvalidInputException;

/**
 * Node Map Generation, JSON-LD 1.1 API section 5.2: merges the node
 * objects of an expanded document into a map of graphs, each holding one
 * node per subject. Blank nodes are relabeled `_:b0`, `_:b1`, … in the
 * order the algorithm meets them.
 *
 * @internal
 *
 * @psalm-import-type JsonValue from \Rasuvaeff\JsonLd\Json
 */
final class NodeMap
{
    /** @var array<string, array<string, array<string, mixed>>> graph name => subject => node */
    public array $graphs = [Keywords::DEFAULT_GRAPH => []];

    /** @var array<string, string> */
    private array $labels = [];

    private int $counter = 0;

    /**
     * @param list<mixed> $expanded
     */
    public static function of(array $expanded): self
    {
        $map = new self();
        $map->generate($expanded);

        return $map;
    }

    /**
     * Blank Node Identifier generation: the same input label always maps to
     * the same output label; null issues a fresh one.
     */
    public function issue(?string $identifier = null): string
    {
        if ($identifier !== null && isset($this->labels[$identifier])) {
            return $this->labels[$identifier];
        }

        $issued = '_:b' . $this->counter++;

        if ($identifier !== null) {
            $this->labels[$identifier] = $issued;
        }

        return $issued;
    }

    /**
     * @param array<string, mixed>|string|null $activeSubject a subject id, a reference map for a reverse property, or null
     * @param array<string, list<JsonValue>>|null $list the list object being filled
     */
    public function generate(
        mixed $element,
        string $activeGraph = Keywords::DEFAULT_GRAPH,
        array|string|null $activeSubject = null,
        ?string $activeProperty = null,
        ?array &$list = null,
    ): void {
        if (!is_array($element)) {
            return;
        }

        if (array_is_list($element)) {
            /** @var mixed $item */
            foreach ($element as $item) {
                $this->generate($item, $activeGraph, $activeSubject, $activeProperty, $list);
            }

            return;
        }

        $this->graphs[$activeGraph] ??= [];

        unset($element[ExpansionRun::EMPTY]);

        if (array_key_exists(Keywords::TYPE, $element) && is_array($element[Keywords::TYPE])) {
            $element[Keywords::TYPE] = array_map(
                fn(mixed $type): mixed => is_string($type) && str_starts_with($type, '_:') ? $this->issue($type) : $type,
                $element[Keywords::TYPE],
            );
        }

        if (array_key_exists(Keywords::VALUE, $element)) {
            if ($list === null) {
                if (is_string($activeSubject) && $activeProperty !== null) {
                    $this->add($activeGraph, $activeSubject, $activeProperty, $element, unique: true);
                }
            } else {
                $list[Keywords::LIST][] = $element;
            }

            return;
        }

        if (array_key_exists(Keywords::LIST, $element)) {
            $result = [Keywords::LIST => []];
            $this->generate($element[Keywords::LIST], $activeGraph, $activeSubject, $activeProperty, $result);

            if ($list === null) {
                if (is_string($activeSubject) && $activeProperty !== null) {
                    $this->add($activeGraph, $activeSubject, $activeProperty, $result, unique: false);
                }
            } else {
                $list[Keywords::LIST][] = $result;
            }

            return;
        }

        if (array_key_exists(Keywords::ID, $element)) {
            // A null identifier (an ignored keyword-like @id) names nothing
            // well-formed; the node stays but never reaches RDF.
            $id = is_string($element[Keywords::ID]) ? $element[Keywords::ID] : '';
            $id = str_starts_with($id, '_:') ? $this->issue($id) : $id;
        } else {
            $id = $this->issue();
        }

        unset($element[Keywords::ID]);

        $this->graphs[$activeGraph][$id] ??= [Keywords::ID => $id];

        if (is_array($activeSubject)) {
            $this->add($activeGraph, $id, $activeProperty ?? '', $activeSubject, unique: true);
        } elseif ($activeProperty !== null && $activeSubject !== null) {
            $reference = [Keywords::ID => $id];

            if ($list === null) {
                $this->add($activeGraph, $activeSubject, $activeProperty, $reference, unique: true);
            } else {
                $list[Keywords::LIST][] = $reference;
            }
        }

        if (array_key_exists(Keywords::TYPE, $element)) {
            /** @var JsonValue $type */
            foreach (is_array($element[Keywords::TYPE]) ? $element[Keywords::TYPE] : [$element[Keywords::TYPE]] as $type) {
                if (is_string($type)) {
                    $this->add($activeGraph, $id, Keywords::TYPE, $type, unique: true);
                }
            }

            unset($element[Keywords::TYPE]);
        }

        if (array_key_exists(Keywords::INDEX, $element)) {
            /** @var JsonValue $existing */
            $existing = $this->graphs[$activeGraph][$id][Keywords::INDEX] ?? null;

            if ($existing !== null && $existing !== $element[Keywords::INDEX]) {
                throw new InvalidInputException('conflicting indexes');
            }

            $this->graphs[$activeGraph][$id][Keywords::INDEX] = $element[Keywords::INDEX];
            unset($element[Keywords::INDEX]);
        }

        if (array_key_exists(Keywords::REVERSE, $element) && is_array($element[Keywords::REVERSE])) {
            $reference = [Keywords::ID => $id];

            /** @var JsonValue $values */
            foreach ($element[Keywords::REVERSE] as $property => $values) {
                /** @var JsonValue $value */
                foreach (is_array($values) ? $values : [] as $value) {
                    $this->generate($value, $activeGraph, $reference, strval($property));
                }
            }

            unset($element[Keywords::REVERSE]);
        }

        if (array_key_exists(Keywords::GRAPH, $element)) {
            $this->graphs[$id] ??= [];
            $this->generate($element[Keywords::GRAPH], $id);
            unset($element[Keywords::GRAPH]);
        }

        if (array_key_exists(Keywords::INCLUDED, $element)) {
            $this->generate($element[Keywords::INCLUDED], $activeGraph);
            unset($element[Keywords::INCLUDED]);
        }

        $properties = array_map(strval(...), array_keys($element));
        sort($properties, SORT_STRING);

        foreach ($properties as $property) {
            $target = str_starts_with($property, '_:') ? $this->issue($property) : $property;

            $this->graphs[$activeGraph][$id][$target] ??= [];
            $this->generate($element[$property], $activeGraph, $id, $target);
        }
    }

    /**
     * Appends a value to the property of a node, optionally only when an
     * equal value is not there yet.
     *
     * @param JsonValue $value
     */
    private function add(string $graph, string $subject, string $property, mixed $value, bool $unique): void
    {
        /** @var list<JsonValue> $values */
        $values = $this->graphs[$graph][$subject][$property] ?? [];

        if (!$unique || !self::contains($values, $value)) {
            $values[] = $value;
        }

        $this->graphs[$graph][$subject][$property] = $values;
    }

    /**
     * @param list<JsonValue> $haystack
     * @param JsonValue $needle
     */
    private static function contains(array $haystack, mixed $needle): bool
    {
        foreach ($haystack as $item) {
            if (self::sameJson($item, $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function sameJson(mixed $left, mixed $right): bool
    {
        if (!is_array($left) || !is_array($right)) {
            return $left === $right;
        }

        if (count($left) !== count($right)) {
            return false;
        }

        /** @var JsonValue $value */
        foreach ($left as $key => $value) {
            if (!array_key_exists($key, $right) || !self::sameJson($value, $right[$key])) {
                return false;
            }
        }

        return true;
    }
}
