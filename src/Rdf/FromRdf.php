<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Rdf;

use Rasuvaeff\JsonLd\Json;
use Rasuvaeff\JsonLd\Keywords;

/**
 * RDF deserialization into expanded JSON-LD, JSON-LD 1.1 API section 5.3
 * ("serialize RDF as JSON-LD"): rebuilds node objects from quads, detects
 * rdf:first/rdf:rest chains back into `@list`, and wraps named graphs.
 *
 * @api
 */
final class FromRdf
{
    private const string XSD_BOOLEAN = 'http://www.w3.org/2001/XMLSchema#boolean';

    private const string XSD_INTEGER = 'http://www.w3.org/2001/XMLSchema#integer';

    private const string XSD_DOUBLE = 'http://www.w3.org/2001/XMLSchema#double';

    public function __construct(
        private readonly bool $useNativeTypes = false,
        private readonly bool $useRdfType = false,
    ) {}

    /**
     * @return list<mixed> expanded JSON-LD document
     */
    public function fromRdf(Dataset $dataset): array
    {
        /** @var array<string, list<Quad>> $byGraph quads grouped by graph name */
        $byGraph = [];

        foreach ($dataset->quads as $quad) {
            $byGraph[self::graphKey($quad)][] = $quad;
        }

        $result = [];

        foreach ($byGraph as $graphKey => $quads) {
            $nodes = $this->graphNodes($quads);

            if ($graphKey === '@default') {
                $result = [...$result, ...$nodes];
            } else {
                $result[] = [
                    Keywords::ID => $graphKey,
                    Keywords::GRAPH => $nodes,
                ];
            }
        }

        return $result;
    }

    /**
     * Walks an rdf:first/rdf:rest chain from its head cell.
     *
     * @param array<string, array{first: mixed, rest: ?string}> $cells every list cell keyed by subject
     *
     * @return list<mixed>
     */
    private function buildList(string $head, array $cells): ?array
    {
        $items = [];
        $current = $head;
        $visited = [];

        while ($current !== Literal::RDF_NIL) {
            if (isset($visited[$current])) {
                // A cycle: this is not a well-formed list.
                return null;
            }

            $visited[$current] = true;

            $entry = $cells[$current] ?? null;

            if ($entry === null) {
                return null;
            }

            $items[] = $entry['first'];
            $current = $entry['rest'];
        }

        return $items;
    }

    /**
     * @param list<Quad> $quads
     *
     * @return list<array<string, mixed>>
     */
    private function graphNodes(array $quads): array
    {
        $cells = $this->listCells($quads);

        /** @var array<string, true> $restTargets */
        $restTargets = [];

        foreach ($quads as $quad) {
            if ($quad->predicate->value === Literal::RDF_REST) {
                $restTargets[self::termKey($quad->object)] = true;
            }
        }

        // Well-formed lists: every cell of a valid chain is consumed by it.
        $heads = [];
        $consumed = [];

        foreach ($cells as $subjectKey => $cell) {
            if (isset($restTargets[$subjectKey])) {
                continue;
            }

            $list = $this->buildList($subjectKey, $cells);

            if ($list !== null) {
                $heads[$subjectKey] = $list;

                $current = $subjectKey;

                while ($current !== Literal::RDF_NIL && isset($cells[$current])) {
                    $consumed[$current] = true;
                    $current = $cells[$current]['rest'];
                }
            }
        }

        /** @var array<string, true> $firstRefs subjects referenced through rdf:first */
        $firstRefs = [];

        foreach ($quads as $quad) {
            if ($quad->predicate->value === Literal::RDF_FIRST) {
                $firstRefs[self::termKey($quad->object)] = true;
            }
        }

        /** @var array<string, array<string, list<array<string, mixed>>>> $bySubject */
        $bySubject = [];

        foreach ($quads as $quad) {
            $subjectKey = self::termKey($quad->subject);
            $predicate = $quad->predicate->value;

            if (($predicate === Literal::RDF_FIRST || $predicate === Literal::RDF_REST)
                && isset($consumed[$subjectKey])
                && !isset($firstRefs[$subjectKey])
            ) {
                continue;
            }

            foreach ($this->predicateValues($quad, $cells, $restTargets, $heads) as $predicate => $value) {
                $bySubject[$subjectKey][$predicate][] = $value;
            }
        }

        $nodes = [];

        foreach ($bySubject as $subjectKey => $properties) {
            $node = [];

            if (!str_starts_with($subjectKey, '_:')) {
                $node[Keywords::ID] = $subjectKey;
            }

            foreach ($properties as $predicate => $values) {
                $node[$predicate] = $values;
            }

            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * @param array<string, array{first: mixed, rest: ?string}> $cells
     * @param array<string, true> $restTargets
     *
     * @return array<string, mixed> property => value entry
     */
    private function predicateValues(Quad $quad, array $cells, array $restTargets, array $heads): array
    {
        $predicate = $quad->predicate->value;

        if ($predicate === Literal::RDF_TYPE) {
            $object = $quad->object;

            if ($object instanceof Iri) {
                if ($this->useRdfType) {
                    return [$predicate => [Keywords::ID => $object->value]];
                }

                return [Keywords::TYPE => $object->value];
            }

            return [];
        }

        if ($predicate === Literal::RDF_FIRST || $predicate === Literal::RDF_REST) {
            return [];
        }

        $objectKey = self::termKey($quad->object);

        if (isset($heads[$objectKey])) {
            return [$predicate => [Keywords::LIST => $heads[$objectKey]]];
        }

        return [$predicate => $this->objectValue($quad->object)];
    }

    /**
     * Every rdf:first/rdf:rest cell keyed by subject, with the first/rest
     * values already converted.
     *
     * @param list<Quad> $quads
     *
     * @return array<string, array{first: mixed, rest: ?string}>
     */
    private function listCells(array $quads): array
    {
        /** @var array<string, array<string, Quad>> $chains */
        $chains = [];

        foreach ($quads as $quad) {
            if ($quad->predicate->value === Literal::RDF_FIRST || $quad->predicate->value === Literal::RDF_REST) {
                $chains[self::termKey($quad->subject)][$quad->predicate->value] = $quad;
            }
        }

        $cells = [];

        foreach ($chains as $subjectKey => $chain) {
            $first = $chain[Literal::RDF_FIRST] ?? null;
            $rest = $chain[Literal::RDF_REST] ?? null;

            if ($first === null || $rest === null) {
                continue;
            }

            $cells[$subjectKey] = [
                'first' => $this->objectValue($first->object),
                'rest' => self::termKey($rest->object),
            ];
        }

        return $cells;
    }

    /**
     * @return array<string, mixed>
     */
    private function objectValue(Iri|BlankNode|Literal $object): array
    {
        if ($object instanceof Iri) {
            return [Keywords::ID => $object->value];
        }

        if ($object instanceof BlankNode) {
            return [Keywords::ID => '_:' . $object->id];
        }

        $result = [Keywords::VALUE => $object->value];

        if ($object->language !== null) {
            $result[Keywords::LANGUAGE] = $object->language;

            return $result;
        }

        $datatype = $object->datatype ?? Literal::XSD_STRING;

        if ($datatype === Literal::RDF_JSON) {
            $decoded = Json::decode($object->value);

            return [Keywords::VALUE => $decoded, Keywords::TYPE => Keywords::JSON];
        }

        if ($this->useNativeTypes) {
            $native = $this->native($object->value, $datatype);

            if ($native !== null) {
                $result[Keywords::VALUE] = $native;

                if ($datatype === Literal::XSD_STRING) {
                    return $result;
                }

                $result[Keywords::TYPE] = $datatype;

                return $result;
            }
        }

        if ($datatype !== Literal::XSD_STRING) {
            $result[Keywords::TYPE] = $datatype;
        }

        return $result;
    }

    private function native(string $value, string $datatype): string|int|float|bool|null
    {
        return match ($datatype) {
            self::XSD_BOOLEAN => $value === 'true',
            self::XSD_INTEGER => filter_var($value, FILTER_VALIDATE_INT),
            self::XSD_DOUBLE => is_numeric($value) ? (float) $value : null,
            default => null,
        };
    }

    private static function termKey(Iri|BlankNode|Literal $term): string
    {
        if ($term instanceof Iri) {
            return $term->value;
        }

        if ($term instanceof BlankNode) {
            return '_:' . $term->id;
        }

        return hash('sha256', $term->value . '|' . ($term->language ?? '') . '|' . ($term->datatype ?? ''));
    }

    private static function graphKey(Quad $quad): string
    {
        if ($quad->graph === null) {
            return '@default';
        }

        return self::termKey($quad->graph);
    }
}
