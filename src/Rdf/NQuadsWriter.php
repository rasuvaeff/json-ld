<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Rdf;

/**
 * Writes RDF terms and statements in canonical N-Quads form (RDF 1.1
 * N-Quads section 4, as refined by RDF Dataset Canonicalization): IRIs as
 * they are, `xsd:string` literals without a datatype, `\`, `"` and the
 * control characters escaped.
 *
 * @internal
 */
final class NQuadsWriter
{
    /**
     * One statement without the line break; `$label` replaces the label of
     * each blank node when given.
     *
     * @param (\Closure(BlankNode): string)|null $label
     */
    public static function statement(Quad $quad, ?\Closure $label = null): string
    {
        $line = self::term($quad->subject, $label) . ' '
            . self::term($quad->predicate) . ' '
            . self::term($quad->object, $label);

        if ($quad->graph !== null) {
            $line .= ' ' . self::term($quad->graph, $label);
        }

        return $line . ' .';
    }

    /**
     * @param (\Closure(BlankNode): string)|null $label
     */
    public static function term(Iri|BlankNode|Literal $term, ?\Closure $label = null): string
    {
        if ($term instanceof Iri) {
            return '<' . $term->value . '>';
        }

        if ($term instanceof BlankNode) {
            return '_:' . ($label instanceof \Closure ? $label($term) : $term->id);
        }

        $result = '"' . self::escape($term->value) . '"';

        if ($term->language !== null) {
            return $result . '@' . $term->language;
        }

        if ($term->datatype !== null && $term->datatype !== Vocabulary::XSD_STRING) {
            return $result . '^^<' . $term->datatype . '>';
        }

        return $result;
    }

    /**
     * Escapes the characters N-Quads canonical form escapes: `\`, `"` and
     * the control characters.
     */
    private static function escape(string $value): string
    {
        return preg_replace_callback(
            '~[\x00-\x1F\x22\x5C\x7F]~',
            static fn(array $m): string => match ($m[0]) {
                "\x08" => '\\b',
                "\t" => '\\t',
                "\n" => '\\n',
                "\x0C" => '\\f',
                "\r" => '\\r',
                '"' => '\\"',
                '\\' => '\\\\',
                default => sprintf('\\u%04X', ord($m[0])),
            },
            $value,
        ) ?? $value;
    }
}
