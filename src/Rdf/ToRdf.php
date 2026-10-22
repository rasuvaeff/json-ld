<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Rdf;

use Rasuvaeff\JsonLd\Context\Syntax;
use Rasuvaeff\JsonLd\Expander;
use Rasuvaeff\JsonLd\ExpandOptions;
use Rasuvaeff\JsonLd\Keywords;
use Rasuvaeff\JsonLd\NodeMap;

/**
 * RDF serialization of JSON-LD, JSON-LD 1.1 API section 8.1 ("Deserialize
 * JSON-LD to RDF"): the node map of the expanded document is turned into
 * quads; lists become rdf:first/rdf:rest chains.
 *
 * Nodes, properties and values that are not well-formed IRIs or blank node
 * identifiers are skipped, as the algorithm requires.
 *
 * @api
 *
 * @psalm-import-type JsonValue from \Rasuvaeff\JsonLd\Json
 */
final readonly class ToRdf
{
    public const string I18N = 'https://www.w3.org/ns/i18n#';

    private const string XSD_BOOLEAN = 'http://www.w3.org/2001/XMLSchema#boolean';

    private const string XSD_INTEGER = 'http://www.w3.org/2001/XMLSchema#integer';

    private const string XSD_DOUBLE = 'http://www.w3.org/2001/XMLSchema#double';

    private const string RDF_VALUE = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#value';

    private const string RDF_LANGUAGE = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#language';

    private const string RDF_DIRECTION = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#direction';

    /**
     * @param string|null $rdfDirection `i18n-datatype`, `compound-literal` or null (base direction is dropped)
     */
    public function __construct(
        private Expander $expander = new Expander(),
        private bool $produceGeneralizedRdf = false,
        private ?string $rdfDirection = null,
    ) {}

    /**
     * @param string|array<mixed> $input
     */
    public function toRdf(string|array $input, ?ExpandOptions $options = null): Dataset
    {
        $map = NodeMap::of($this->expander->expandRaw($input, $options));
        $graphs = $map->graphs;
        ksort($graphs, SORT_STRING);

        $quads = [];

        foreach ($graphs as $name => $graph) {
            $name = strval($name);

            if ($name === Keywords::DEFAULT_GRAPH) {
                $graphTerm = null;
            } elseif ($this->wellFormed($name)) {
                $graphTerm = $this->node($name);
            } else {
                continue;
            }

            ksort($graph, SORT_STRING);

            foreach ($graph as $subject => $node) {
                $subject = strval($subject);

                if (!$this->wellFormed($subject)) {
                    continue;
                }

                $subjectTerm = $this->node($subject);
                $properties = array_map(strval(...), array_keys($node));
                sort($properties, SORT_STRING);

                foreach ($properties as $property) {
                    /** @var JsonValue $values */
                    /** @var JsonValue $values */
                    $values = $node[$property];
                    $values = is_array($values) ? $values : [];

                    if ($property === Keywords::ID) {
                        continue;
                    }

                    if ($property === Keywords::TYPE) {
                        /** @var JsonValue $type */
                        foreach ($values as $type) {
                            if (is_string($type) && $this->wellFormed($type)) {
                                $quads[] = new Quad($subjectTerm, new Iri(Literal::RDF_TYPE), $this->node($type), $graphTerm);
                            }
                        }

                        continue;
                    }

                    if (Keywords::isKeyword($property) || str_starts_with($property, '@')) {
                        continue;
                    }

                    if (Syntax::isBlankNode($property) && !$this->produceGeneralizedRdf) {
                        continue;
                    }

                    if (!$this->wellFormed($property)) {
                        continue;
                    }

                    $predicate = new Iri($property);

                    /** @var JsonValue $item */
                    foreach ($values as $item) {
                        $object = $this->objectToRdf($item, $quads, $graphTerm, $map);

                        if ($object !== null) {
                            $quads[] = new Quad($subjectTerm, $predicate, $object, $graphTerm);
                        }
                    }
                }
            }
        }

        return new Dataset($quads);
    }

    /**
     * @param list<Quad> $quads receives the quads of lists and compound literals
     */
    private function objectToRdf(mixed $item, array &$quads, Iri|BlankNode|null $graph, NodeMap $map): Iri|BlankNode|Literal|null
    {
        if (!is_array($item)) {
            return null;
        }

        /** @var array<string, mixed> $item */
        if (array_key_exists(Keywords::LIST, $item)) {
            /** @var JsonValue $list */
            $list = $item[Keywords::LIST];

            return $this->listToRdf(is_array($list) ? array_values($list) : [], $quads, $graph, $map);
        }

        if (array_key_exists(Keywords::VALUE, $item)) {
            return $this->valueToRdf($item, $quads, $graph, $map);
        }

        $id = $item[Keywords::ID] ?? null;

        if (!is_string($id) || !$this->wellFormed($id)) {
            return null;
        }

        return $this->node($id);
    }

    /**
     * @param list<mixed> $list
     * @param list<Quad> $quads
     */
    private function listToRdf(array $list, array &$quads, Iri|BlankNode|null $graph, NodeMap $map): Iri|BlankNode
    {
        if ($list === []) {
            return new Iri(Literal::RDF_NIL);
        }

        $cells = [];

        foreach ($list as $_) {
            $cells[] = new BlankNode(substr($map->issue(), 2));
        }

        /** @var JsonValue $item */
        foreach ($list as $index => $item) {
            $embedded = [];
            $object = $this->objectToRdf($item, $embedded, $graph, $map);

            if ($object !== null) {
                $quads[] = new Quad($cells[$index], new Iri(Literal::RDF_FIRST), $object, $graph);
            }

            array_push($quads, ...$embedded);

            $quads[] = new Quad(
                $cells[$index],
                new Iri(Literal::RDF_REST),
                $cells[$index + 1] ?? new Iri(Literal::RDF_NIL),
                $graph,
            );
        }

        return $cells[0];
    }

    /**
     * @param array<string, mixed> $item
     * @param list<Quad> $quads
     */
    private function valueToRdf(array $item, array &$quads, Iri|BlankNode|null $graph, NodeMap $map): \Rasuvaeff\JsonLd\Rdf\BlankNode|\Rasuvaeff\JsonLd\Rdf\Literal|null
    {
        /** @var JsonValue $value */
        $value = $item[Keywords::VALUE];
        $datatype = $item[Keywords::TYPE] ?? null;
        $language = $item[Keywords::LANGUAGE] ?? null;

        if ($datatype !== null && (!is_string($datatype) || ($datatype !== Keywords::JSON && !$this->wellFormed($datatype)))) {
            return null;
        }

        if ($language !== null && (!is_string($language) || preg_match('~^[A-Za-z]{1,8}(?:-[A-Za-z0-9]{1,8})*\z~', $language) !== 1)) {
            return null;
        }

        if ($datatype === Keywords::JSON) {
            return Literal::typed(JsonCanonicalizer::serialize($value), Literal::RDF_JSON);
        }

        if (is_bool($value)) {
            $lexical = $value ? 'true' : 'false';
            $datatype ??= self::XSD_BOOLEAN;
        } elseif ((is_float($value) && (fmod($value, 1.0) !== 0.0 || abs($value) >= 1e21)) || (is_int($value) || is_float($value)) && $datatype === self::XSD_DOUBLE) {
            $lexical = $this->doubleCanonical((float) $value);
            $datatype ??= self::XSD_DOUBLE;
        } elseif (is_int($value) || is_float($value)) {
            $lexical = is_int($value) ? (string) $value : sprintf('%.0f', $value);
            $datatype ??= self::XSD_INTEGER;
        } else {
            $lexical = is_string($value) ? $value : '';
        }

        /** @var JsonValue $direction */
        $direction = $item[Keywords::DIRECTION] ?? null;

        if ($direction !== null && $this->rdfDirection === 'i18n-datatype') {
            return Literal::typed($lexical, self::I18N . strtolower(is_string($language) ? $language : '') . '_' . (is_string($direction) ? $direction : ''));
        }

        if ($direction !== null && $this->rdfDirection === 'compound-literal') {
            $node = new BlankNode(substr($map->issue(), 2));
            $quads[] = new Quad($node, new Iri(self::RDF_VALUE), Literal::string($lexical), $graph);

            if ($language !== null) {
                $quads[] = new Quad($node, new Iri(self::RDF_LANGUAGE), Literal::string(strtolower($language)), $graph);
            }

            $quads[] = new Quad($node, new Iri(self::RDF_DIRECTION), Literal::string(is_string($direction) ? $direction : ''), $graph);

            return $node;
        }

        if ($language !== null) {
            return Literal::langString($lexical, $language);
        }

        return Literal::typed($lexical, $datatype ?? Literal::XSD_STRING);
    }

    private function wellFormed(string $id): bool
    {
        return Syntax::isBlankNode($id) || Syntax::isAbsoluteIri($id);
    }

    private function node(string $id): Iri|BlankNode
    {
        return Syntax::isBlankNode($id) ? new BlankNode(substr($id, 2)) : new Iri($id);
    }

    /**
     * XSD double canonical form: mantissa with at least one decimal digit,
     * exponent without leading zeros (`1.0E0`, `4.5E0`, `1.0E20`).
     */
    private function doubleCanonical(float $value): string
    {
        if ($value === 0.0) {
            return '0.0E0';
        }

        [$mantissa, $exponent] = explode('E', sprintf('%.15E', $value));

        $mantissa = rtrim($mantissa, '0');

        if (str_ends_with($mantissa, '.')) {
            $mantissa .= '0';
        }

        return $mantissa . 'E' . (int) $exponent;
    }
}
