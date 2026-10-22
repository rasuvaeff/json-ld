<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Graph;

/**
 * A node of a JSON-LD graph: an immutable view over one expanded node
 * object, with accessors for values and references to other nodes.
 *
 * @api
 */
final readonly class Node
{
    /**
     * @param array<string, mixed> $expanded the expanded node object
     * @param Graph $graph the graph the node belongs to, for reference resolution
     */
    public function __construct(
        private array $expanded,
        private Graph $graph,
    ) {}

    public function id(): ?string
    {
        $id = $this->expanded['@id'] ?? null;

        return is_string($id) ? $id : null;
    }

    /**
     * @return list<string> absolute IRIs from `@type`
     */
    public function types(): array
    {
        $types = $this->expanded['@type'] ?? [];

        if (is_string($types)) {
            return [$types];
        }

        return is_array($types) ? array_values($types) : [];
    }

    public function hasType(string $iri): bool
    {
        return in_array($iri, $this->types(), true);
    }

    /**
     * All properties in their expanded form, keyed by absolute IRI.
     *
     * @return array<string, list<mixed>>
     */
    public function properties(): array
    {
        $result = [];

        foreach ($this->expanded as $key => $value) {
            if ($key === '@id' || $key === '@type') {
                continue;
            }

            $result[$key] = is_array($value) && array_is_list($value) ? $value : [$value];
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

        foreach ($this->expanded[$property] ?? [] as $value) {
            if (is_array($value) && !array_is_list($value) && array_key_exists('@value', $value)) {
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

        foreach ($this->expanded[$property] ?? [] as $value) {
            if (is_array($value) && !array_is_list($value) && isset($value['@id']) && is_string($value['@id'])) {
                $node = $this->graph->node($value['@id']);

                if ($node !== null) {
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
     * The raw expanded node object this view wraps.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->expanded;
    }
}
