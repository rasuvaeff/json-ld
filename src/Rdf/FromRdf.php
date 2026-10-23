<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Rdf;

use ArrayObject;
use JsonException;
use Rasuvaeff\JsonLd\Context\Syntax;
use Rasuvaeff\JsonLd\Exception\InvalidInputException;
use Rasuvaeff\JsonLd\Keywords;

/**
 * Serialization of RDF as JSON-LD, JSON-LD 1.1 API section 8.2: rebuilds
 * node objects from quads, restores well-formed rdf:first/rdf:rest chains
 * into `@list` and wraps named graphs.
 *
 * The result is a document in expanded form.
 *
 * @internal
 *
 * @psalm-import-type JsonValue from \Rasuvaeff\JsonLd\Json
 * @psalm-type NodeShape = array{id: string, types: list<string>, props: array<string, list<ArrayObject<string, mixed>>>}
 * @psalm-type Usage = array{subject: string, property: string, slot: ArrayObject<string, mixed>}
 */
final readonly class FromRdf
{
    private const string XSD_BOOLEAN = 'http://www.w3.org/2001/XMLSchema#boolean';

    private const string XSD_INTEGER = 'http://www.w3.org/2001/XMLSchema#integer';

    private const string XSD_DOUBLE = 'http://www.w3.org/2001/XMLSchema#double';

    private const string RDF_LIST = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#List';

    private const string RDF_VALUE = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#value';

    private const string RDF_LANGUAGE = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#language';

    private const string RDF_DIRECTION = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#direction';

    /**
     * @param string|null $rdfDirection `i18n-datatype`, `compound-literal` or null
     */
    public function __construct(
        private bool $useNativeTypes = false,
        private bool $useRdfType = false,
        private ?string $rdfDirection = null,
    ) {}

    /**
     * @return list<mixed> expanded JSON-LD document
     *
     * @throws InvalidInputException `invalid JSON literal` for an rdf:JSON literal that is not JSON
     */
    public function fromRdf(Dataset $dataset): array
    {
        /** @var array<string, array<string, NodeShape>> $graphMap graph => subject => node */
        $graphMap = [Keywords::DEFAULT_GRAPH => []];
        /** @var array<string, array<string, list<Usage>>> $usages */
        $usages = [];
        /** @var array<string, array<string, true>> $compoundSubjects */
        $compoundSubjects = [];

        foreach ($dataset->quads as $quad) {
            $name = $quad->graph === null ? Keywords::DEFAULT_GRAPH : $this->id($quad->graph);

            $graphMap[$name] ??= [];

            if ($name !== Keywords::DEFAULT_GRAPH) {
                $graphMap[Keywords::DEFAULT_GRAPH][$name] ??= $this->emptyNode($name);
            }

            $subject = $this->id($quad->subject);
            $graphMap[$name][$subject] ??= $this->emptyNode($subject);

            $object = $quad->object;

            if (!$object instanceof Literal) {
                $objectId = $this->id($object);
                $graphMap[$name][$objectId] ??= $this->emptyNode($objectId);
            }

            $property = $quad->predicate->value;

            if ($this->rdfDirection === 'compound-literal' && $property === self::RDF_DIRECTION) {
                $compoundSubjects[$name][$subject] = true;
            }

            if ($property === Literal::RDF_TYPE && !$this->useRdfType && !$object instanceof Literal) {
                $objectId = $this->id($object);

                if (!in_array($objectId, $graphMap[$name][$subject]['types'], strict: true)) {
                    $graphMap[$name][$subject]['types'][] = $objectId;
                }

                continue;
            }

            $value = new ArrayObject($this->objectToValue($object));
            $values = $graphMap[$name][$subject]['props'][$property] ?? [];
            $isNew = true;

            foreach ($values as $existing) {
                if ($existing->getArrayCopy() === $value->getArrayCopy()) {
                    $value = $existing;
                    $isNew = false;

                    break;
                }
            }

            if ($isNew) {
                $values[] = $value;
            }

            $graphMap[$name][$subject]['props'][$property] = $values;

            if (!$object instanceof Literal) {
                $objectId = $this->id($object);

                if ($isNew && (Syntax::isBlankNodeId($objectId) || $objectId === Literal::RDF_NIL)) {
                    $usages[$name][$objectId][] = ['subject' => $subject, 'property' => $property, 'slot' => $value];
                }
            }
        }

        /** @var array<string, int> $usageTotals */
        $usageTotals = [];

        foreach ($usages as $graphUsages) {
            foreach ($graphUsages as $id => $nodeUsages) {
                $usageTotals[$id] = ($usageTotals[$id] ?? 0) + count($nodeUsages);
            }
        }

        foreach ($graphMap as $name => $graph) {
            $this->collapseLists($graph, $usages[$name] ?? [], $usageTotals);
            $this->collapseCompoundLiterals($graph, $usages[$name] ?? [], $compoundSubjects[$name] ?? []);
            $graphMap[$name] = $graph;
        }

        $default = $graphMap[Keywords::DEFAULT_GRAPH];
        unset($graphMap[Keywords::DEFAULT_GRAPH]);

        /** @var array<string, list<array<string, mixed>>> $named */
        $named = [];

        foreach ($graphMap as $name => $graph) {
            ksort($graph, SORT_STRING);
            $entries = [];

            foreach ($graph as $node) {
                if (!$this->isBare($node)) {
                    $entries[] = $this->render($node);
                }
            }

            $named[strval($name)] = $entries;
        }

        ksort($default, SORT_STRING);
        $result = [];

        foreach ($default as $id => $node) {
            $rendered = $this->render($node);

            if (isset($named[$id])) {
                $rendered[Keywords::GRAPH] = $named[$id];
            }

            if (count($rendered) > 1) {
                $result[] = $rendered;
            }
        }

        return $this->finish($result);
    }

    /**
     * Value objects are shared handles while the graph is being built, so
     * that a list head replaced later is seen by every list holding it.
     *
     * @return list<mixed>
     */
    private function finish(mixed $value): array
    {
        /** @var list<mixed> */
        return self::unwrap($value);
    }

    private static function unwrap(mixed $value): mixed
    {
        if ($value instanceof ArrayObject) {
            $value = $value->getArrayCopy();
        }

        return is_array($value) ? array_map(self::unwrap(...), $value) : $value;
    }

    /**
     * @return NodeShape
     */
    private function emptyNode(string $id): array
    {
        return ['id' => $id, 'types' => [], 'props' => []];
    }

    /**
     * @param NodeShape $node
     */
    private function isBare(array $node): bool
    {
        return $node['types'] === [] && $node['props'] === [];
    }

    /**
     * @param NodeShape $node
     *
     * @return array<string, mixed>
     */
    private function render(array $node): array
    {
        $result = [Keywords::ID => $node['id']];

        if ($node['types'] !== []) {
            $result[Keywords::TYPE] = $node['types'];
        }

        foreach ($node['props'] as $property => $values) {
            $result[$property] = $values;
        }

        return $result;
    }

    /**
     * Replaces well-formed rdf:first/rdf:rest chains with list objects.
     *
     * @param array<string, NodeShape> $graph
     * @param array<string, list<Usage>> $usages
     * @param array<string, int> $usageTotals usages of every node across all graphs
     */
    private function collapseLists(array &$graph, array $usages, array $usageTotals): void
    {
        foreach ($usages[Literal::RDF_NIL] ?? [] as $usage) {
            $node = $usage['subject'];
            $property = $usage['property'];
            $head = $usage['slot'];

            /** @var list<ArrayObject<string, mixed>> $list */
            $list = [];
            $listNodes = [];

            while ($property === Literal::RDF_REST
                && Syntax::isBlankNodeId($node)
                && isset($graph[$node], $usages[$node][0])
                && $this->isWellFormedListNode($graph[$node], count($usages[$node]), $usageTotals[$node] ?? 0)
            ) {
                array_unshift($list, $graph[$node]['props'][Literal::RDF_FIRST][0]);
                array_unshift($listNodes, $node);

                $usage = $usages[$node][0];
                $node = $usage['subject'];
                $property = $usage['property'];
                $head = $usage['slot'];
            }

            $head->exchangeArray([Keywords::LIST => $list]);

            foreach ($listNodes as $listNodeId) {
                unset($graph[$listNodeId]);
            }
        }
    }

    /**
     * @param NodeShape $node
     */
    private function isWellFormedListNode(array $node, int $usages, int $total): bool
    {
        if ($usages !== 1 || $total !== 1) {
            return false;
        }

        $properties = array_keys($node['props']);
        sort($properties);

        return $properties === [Literal::RDF_FIRST, Literal::RDF_REST]
            && count($node['props'][Literal::RDF_FIRST]) === 1
            && count($node['props'][Literal::RDF_REST]) === 1
            && ($node['types'] === [] || $node['types'] === [self::RDF_LIST]);
    }

    /**
     * Turns compound literal nodes (rdf:value, rdf:language, rdf:direction)
     * back into value objects.
     *
     * @param array<string, NodeShape> $graph
     * @param array<string, list<Usage>> $usages
     * @param array<string, true> $compoundSubjects
     */
    private function collapseCompoundLiterals(array &$graph, array $usages, array $compoundSubjects): void
    {
        foreach (array_keys($compoundSubjects) as $id) {
            $nodeUsages = $usages[$id] ?? [];

            if (count($nodeUsages) !== 1 || !isset($graph[$id])) {
                continue;
            }

            $props = $graph[$id]['props'];
            /** @var array<string, JsonValue> $literal */
            $literal = [Keywords::VALUE => ($props[self::RDF_VALUE][0] ?? null)?->offsetGet(Keywords::VALUE)];

            /** @var JsonValue $language */
            $language = ($props[self::RDF_LANGUAGE][0] ?? null)?->offsetGet(Keywords::VALUE);

            if ($language !== null) {
                $literal[Keywords::LANGUAGE] = $language;
            }

            /** @var JsonValue $direction */
            $direction = ($props[self::RDF_DIRECTION][0] ?? null)?->offsetGet(Keywords::VALUE);

            if ($direction !== null) {
                $literal[Keywords::DIRECTION] = $direction;
            }

            $nodeUsages[0]['slot']->exchangeArray($literal);
            unset($graph[$id]);
        }
    }

    /**
     * RDF to Object conversion, API section 8.3.
     *
     * @return array<string, mixed>
     */
    private function objectToValue(Iri|BlankNode|Literal $term): array
    {
        if (!$term instanceof Literal) {
            return [Keywords::ID => $this->id($term)];
        }

        $datatype = $term->datatype ?? Literal::XSD_STRING;
        $lexical = $term->value;
        $result = [];
        $converted = $lexical;
        $type = null;

        if ($this->useNativeTypes) {
            if ($datatype === Literal::XSD_STRING) {
                $converted = $lexical;
            } elseif ($datatype === self::XSD_BOOLEAN) {
                if (in_array($lexical, ['true', 'false', '1', '0'], strict: true)) {
                    $converted = $lexical === 'true' || $lexical === '1';
                } else {
                    $type = $datatype;
                }
            } elseif ($datatype === self::XSD_INTEGER && $this->fitsInteger($lexical)) {
                $converted = (int) $lexical;
            } elseif ($datatype === self::XSD_DOUBLE
                && preg_match('~^[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?\z~', $lexical) === 1
                && is_finite((float) $lexical)
            ) {
                $converted = (float) $lexical;
            } else {
                $type = $datatype;
            }
        } elseif ($datatype === Literal::RDF_LANGSTRING) {
            $result[Keywords::LANGUAGE] = $term->language;
        } elseif ($datatype === Literal::RDF_JSON) {
            try {
                /** @var JsonValue $converted */
                $converted = json_decode($lexical, associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new InvalidInputException('invalid JSON literal');
            }

            $type = Keywords::JSON;
        } elseif ($this->rdfDirection === 'i18n-datatype' && str_starts_with($datatype, ToRdf::I18N)) {
            $parts = explode('_', substr($datatype, strlen(ToRdf::I18N)), 2);

            if ($parts[0] !== '') {
                $result[Keywords::LANGUAGE] = $parts[0];
            }

            $result[Keywords::DIRECTION] = $parts[1] ?? '';
        } elseif ($datatype !== Literal::XSD_STRING) {
            $type = $datatype;
        }

        if ($this->useNativeTypes && $datatype === Literal::RDF_LANGSTRING) {
            $result[Keywords::LANGUAGE] = $term->language;
            $type = null;
        }

        if ($this->useNativeTypes && $datatype === Literal::RDF_JSON) {
            try {
                /** @var JsonValue $converted */
                $converted = json_decode($lexical, associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new InvalidInputException('invalid JSON literal');
            }

            $type = Keywords::JSON;
        }

        $result[Keywords::VALUE] = $converted;

        if ($type !== null) {
            $result[Keywords::TYPE] = $type;
        }

        return $result;
    }

    private function fitsInteger(string $lexical): bool
    {
        if (preg_match('~^([+-]?)0*([0-9]+)\z~', $lexical, $match) !== 1) {
            return false;
        }

        $normalized = ($match[1] === '-' && $match[2] !== '0' ? '-' : '') . $match[2];

        return (string) (int) $normalized === $normalized;
    }

    private function id(Iri|BlankNode $term): string
    {
        return $term instanceof BlankNode ? '_:' . $term->id : $term->value;
    }
}
