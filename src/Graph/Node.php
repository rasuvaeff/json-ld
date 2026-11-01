<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Graph;

/**
 * A node of a JSON-LD graph: an immutable view over one node object in
 * flattened form, with accessors for values and references to other nodes.
 *
 * @api
 *
 * @psalm-import-type JsonValue from \Rasuvaeff\JsonLd\Json
 */
final readonly class Node
{
    /**
     * @param array<array-key, mixed> $expanded the node object in flattened form: expanded, embedded nodes
     *                                        replaced by `{"@id": …}` references
     * @param Graph $graph the graph the node belongs to, for reference resolution
     */
    public function __construct(
        private array $expanded,
        private Graph $graph,
    ) {}

    public function id(): ?string
    {
        /** @var JsonValue $id */
        $id = $this->expanded['@id'] ?? null;

        return is_string($id) ? $id : null;
    }

    /**
     * @return list<string> absolute IRIs from `@type`
     */
    public function types(): array
    {
        /** @var JsonValue $types */
        $types = $this->expanded['@type'] ?? [];

        if (is_string($types)) {
            return [$types];
        }

        $result = [];

        if (is_array($types)) {
            /** @var JsonValue $type */
            foreach ($types as $type) {
                if (is_string($type)) {
                    $result[] = $type;
                }
            }
        }

        return $result;
    }

    public function hasType(string $iri): bool
    {
        return in_array($iri, $this->types(), strict: true);
    }

    /**
     * All properties in their expanded form, keyed by absolute IRI.
     *
     * @return array<string, list<mixed>>
     */
    public function properties(): array
    {
        /** @var array<string, list<mixed>> $result */
        $result = [];

        foreach (array_keys($this->expanded) as $key) {
            if ($key === '@id' || $key === '@type') {
                continue;
            }

            $result[strval($key)] = $this->items(strval($key));
        }

        return $result;
    }

    /**
     * Values of a property that are value objects, as raw scalars
     * (string|int|float|bool), skipping nested structures.
     *
     * @return list<string|int|float|bool>
     */
    public function values(string $property): array
    {
        $result = [];

        /** @var JsonValue $value */
        foreach ($this->items($property) as $value) {
            if (is_array($value) && !array_is_list($value) && array_key_exists('@value', $value)) {
                /** @var JsonValue $inner */
                $inner = $value['@value'];

                if (is_scalar($inner)) {
                    $result[] = $inner;
                }
            } elseif (is_scalar($value)) {
                $result[] = $value;
            }
        }

        return $result;
    }

    /**
     * Value objects of a property with their language, datatype and
     * direction; values that are not scalars (`@json` objects and arrays)
     * and node references are skipped.
     *
     * @return list<Value>
     */
    public function valueObjects(string $property): array
    {
        $result = [];

        /** @var JsonValue $value */
        foreach ($this->items($property) as $value) {
            if (!is_array($value) || array_is_list($value) || !array_key_exists('@value', $value)) {
                continue;
            }

            /** @var JsonValue $inner */
            $inner = $value['@value'];

            if (is_scalar($inner)) {
                $result[] = new Value(
                    value: $inner,
                    language: $this->stringOrNull($value['@language'] ?? null),
                    type: $this->stringOrNull($value['@type'] ?? null),
                    direction: $this->stringOrNull($value['@direction'] ?? null),
                );
            }
        }

        return $result;
    }

    /**
     * The first scalar value of a property, or null.
     */
    public function value(string $property): string|int|float|bool|null
    {
        return $this->values($property)[0] ?? null;
    }

    /**
     * Nodes referenced by a property through `@id` references, resolved in
     * the owning graph.
     *
     * @return list<Node>
     */
    public function references(string $property): array
    {
        $result = [];

        /** @var JsonValue $value */
        foreach ($this->items($property) as $value) {
            if (is_array($value) && !array_is_list($value) && isset($value['@id']) && is_string($value['@id'])) {
                $node = $this->graph->node($value['@id']);

                if ($node instanceof self) {
                    $result[] = $node;
                }
            }
        }

        return $result;
    }

    public function reference(string $property): ?Node
    {
        return $this->references($property)[0] ?? null;
    }

    /**
     * The node object this view wraps, in flattened form.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        /** @var array<string, mixed> $expanded */
        $expanded = $this->expanded;

        return $expanded;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    /**
     * The values of a member as a list: `@index` and other single values
     * are wrapped.
     *
     * @return list<mixed>
     */
    private function items(string $property): array
    {
        /** @var JsonValue $value */
        $value = $this->expanded[$property] ?? [];

        return is_array($value) && array_is_list($value) ? $value : [$value];
    }
}
