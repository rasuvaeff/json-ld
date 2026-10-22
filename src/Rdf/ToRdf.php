<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Rdf;

use Rasuvaeff\JsonLd\BlankNodeGenerator;
use Rasuvaeff\JsonLd\Expander;
use Rasuvaeff\JsonLd\ExpandOptions;
use Rasuvaeff\JsonLd\Json;
use Rasuvaeff\JsonLd\Keywords;

/**
 * RDF serialization of expanded documents, JSON-LD 1.1 API section 5.2
 * ("deserialize JSON-LD to RDF"): turns node objects into quads, expands
 * lists into rdf:first/rdf:rest chains and separates named graphs.
 *
 * @api
 */
final class ToRdf
{
    private const string XSD_BOOLEAN = 'http://www.w3.org/2001/XMLSchema#boolean';

    private const string XSD_INTEGER = 'http://www.w3.org/2001/XMLSchema#integer';

    private const string XSD_DOUBLE = 'http://www.w3.org/2001/XMLSchema#double';

    public function __construct(private readonly Expander $expander = new Expander()) {}

    /**
     * @param string|array<mixed> $input
     */
    public function toRdf(string|array $input, ?ExpandOptions $options = null): Dataset
    {
        $expanded = $this->expander->expand($input, $options);

        $generator = new BlankNodeGenerator();
        $quads = [];

        foreach ($expanded as $node) {
            $this->emitNode($node, null, $quads, $generator);
        }

        return new Dataset($quads);
    }

    /**
     * @param mixed $node
     * @param list<Quad> $quads
     */
    private function emitNode(mixed $node, Iri|BlankNode|null $graph, array &$quads, BlankNodeGenerator $generator): void
    {
        if (!is_array($node) || array_is_list($node)) {
            return;
        }

        $subject = $this->subjectTerm($node, $generator);

        foreach ($node as $property => $value) {
            if ($property === Keywords::ID) {
                continue;
            }

            if ($property === Keywords::TYPE) {
                foreach (is_string($value) ? [$value] : $value as $type) {
                    if (is_string($type)) {
                        $quads[] = new Quad($subject, new Iri(Literal::RDF_TYPE), new Iri($type), $graph);
                    }
                }

                continue;
            }

            if ($property === Keywords::GRAPH) {
                foreach (is_array($value) && array_is_list($value) ? $value : [$value] as $inner) {
                    $this->emitNode($inner, $subject, $quads, $generator);
                }

                continue;
            }

            if ($property === Keywords::INCLUDED) {
                foreach (is_array($value) && array_is_list($value) ? $value : [$value] as $inner) {
                    $this->emitNode($inner, $graph, $quads, $generator);
                }

                continue;
            }

            if ($property === Keywords::REVERSE) {
                foreach ($value as $reverseProperty => $reverseValues) {
                    foreach ($reverseValues as $reverseNode) {
                        $object = $this->subjectTerm($reverseNode, $generator);

                        if (isset($reverseNode[Keywords::ID])) {
                            $quads[] = new Quad($object, new Iri((string) $reverseProperty), $subject, $graph);
                        }
                    }
                }

                continue;
            }

            if (str_starts_with($property, '@')) {
                continue;
            }

            foreach (is_array($value) && array_is_list($value) ? $value : [$value] as $item) {
                if (!is_array($item) || array_is_list($item)) {
                    continue;
                }

                if (array_key_exists(Keywords::LIST, $item)) {
                    $this->emitList($item[Keywords::LIST], $subject, new Iri($property), $graph, $quads, $generator);

                    continue;
                }

                if (array_key_exists(Keywords::VALUE, $item)) {
                    $quads[] = new Quad($subject, new Iri($property), $this->literal($item), $graph);

                    continue;
                }

                if (isset($item[Keywords::ID]) && is_string($item[Keywords::ID])) {
                    $quads[] = new Quad(
                        $subject,
                        new Iri($property),
                        str_starts_with($item[Keywords::ID], '_:')
                            ? new BlankNode(substr($item[Keywords::ID], 2))
                            : new Iri($item[Keywords::ID]),
                        $graph,
                    );
                }
            }
        }
    }

    /**
     * @param list<mixed> $items
     * @param list<Quad> $quads
     */
    private function emitList(
        array $items,
        Iri|BlankNode $subject,
        Iri $predicate,
        Iri|BlankNode|null $graph,
        array &$quads,
        BlankNodeGenerator $generator,
    ): void {
        $head = new BlankNode(substr($generator->next(), 2));
        $quads[] = new Quad($subject, $predicate, $head, $graph);

        $current = $head;

        foreach ($items as $index => $item) {
            $object = is_array($item) && !array_is_list($item)
                ? ($this->nodeValue($item, $quads, $graph, $generator) ?? $this->literal($item))
                : new Literal((string) $item);

            if (is_array($item) && !array_is_list($item) && isset($item[Keywords::LIST])) {
                $nestedHead = new BlankNode(substr($generator->next(), 2));
                $this->emitList($item[Keywords::LIST], $subject, $predicate, $graph, $quads, $generator);
                $object = $nestedHead;
            }

            $quads[] = new Quad($current, new Iri(Literal::RDF_FIRST), $object, $graph);

            $rest = new BlankNode(substr($generator->next(), 2));
            $quads[] = new Quad($current, new Iri(Literal::RDF_REST), $rest, $graph);
            $current = $rest;
        }

        $quads[] = new Quad($current, new Iri(Literal::RDF_REST), new Iri(Literal::RDF_NIL), $graph);
    }

    /**
     * @param array<string, mixed> $node
     */
    private function subjectTerm(array $node, BlankNodeGenerator $generator): Iri|BlankNode
    {
        $id = $node[Keywords::ID] ?? null;

        if (is_string($id)) {
            return str_starts_with($id, '_:')
                ? new BlankNode(substr($id, 2))
                : new Iri($id);
        }

        return new BlankNode(substr($generator->next(), 2));
    }

    /**
     * @param array<string, mixed> $item
     * @param list<Quad> $quads
     */
    private function nodeValue(array $item, array &$quads, Iri|BlankNode|null $graph, BlankNodeGenerator $generator): Iri|BlankNode|null
    {
        if (isset($item[Keywords::ID]) && is_string($item[Keywords::ID])) {
            return str_starts_with($item[Keywords::ID], '_:')
                ? new BlankNode(substr($item[Keywords::ID], 2))
                : new Iri($item[Keywords::ID]);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function literal(array $item): Literal
    {
        $value = $item[Keywords::VALUE];
        $type = $item[Keywords::TYPE] ?? null;

        if ($type === Keywords::JSON) {
            return Literal::typed(Json::encode($value, pretty: true), Literal::RDF_JSON);
        }

        if (isset($item[Keywords::LANGUAGE])) {
            return Literal::langString((string) $value, $item[Keywords::LANGUAGE]);
        }

        if (is_string($type)) {
            return Literal::typed($this->lexical($value, $type), $type);
        }

        return Literal::typed($this->lexical($value, null), Literal::XSD_STRING);
    }

    /**
     * The lexical form of an expanded scalar for a given datatype.
     */
    private function lexical(mixed $value, ?string $datatype): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return $datatype === self::XSD_DOUBLE ? self::doubleCanonical($value) : self::doubleCanonical($value);
        }

        return (string) $value;
    }

    /**
     * XSD double canonical form: mantissa with one decimal digit minimum,
     * exponent without leading zeros (`1.0E0`, `4.5E0`, `1.0E20`).
     */
    private static function doubleCanonical(float $value): string
    {
        if ($value === 0.0) {
            return '0.0E0';
        }

        $formatted = sprintf('%.15E', $value);

        [$mantissa, $exponent] = explode('E', $formatted);

        if (str_contains($mantissa, '.')) {
            $mantissa = rtrim(rtrim($mantissa, '0'), '.');
        }

        if (!str_contains($mantissa, '.')) {
            $mantissa .= '.0';
        }

        $exponent = ltrim($exponent, '+');

        if (str_starts_with($exponent, '-')) {
            $exponent = '-' . ltrim(substr($exponent, 1), '0');
        } else {
            $exponent = ltrim($exponent, '0');
        }

        return $mantissa . 'E' . ($exponent === '' ? '0' : $exponent);
    }
}
