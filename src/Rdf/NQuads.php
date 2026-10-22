<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Rdf;

use Rasuvaeff\JsonLd\Exception\InvalidNQuadsException;

/**
 * N-Quads serialization and parsing, RDF 1.1 N-Quads.
 *
 * @api
 */
final class NQuads
{
    private const string IRIREF = '<((?:[^\x00-\x20<>"{}|^`\\\\]|\\\\u[0-9A-Fa-f]{4}|\\\\U[0-9A-Fa-f]{8})*)>';

    private const string BLANK = '_:([A-Za-z0-9_\x{00C0}-\x{00D6}\x{00D8}-\x{00F6}\x{00F8}-\x{02FF}\x{0370}-\x{037D}\x{037F}-\x{1FFF}\x{200C}-\x{200D}\x{2070}-\x{218F}\x{2C00}-\x{2FEF}\x{3001}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFFD}\x{10000}-\x{EFFFF}](?:[A-Za-z0-9_\-.\x{00B7}\x{0300}-\x{036F}\x{203F}-\x{2040}\x{00C0}-\x{00D6}\x{00D8}-\x{00F6}\x{00F8}-\x{02FF}\x{0370}-\x{037D}\x{037F}-\x{1FFF}\x{200C}-\x{200D}\x{2070}-\x{218F}\x{2C00}-\x{2FEF}\x{3001}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFFD}\x{10000}-\x{EFFFF}]*[A-Za-z0-9_\-\x{00B7}\x{0300}-\x{036F}\x{203F}-\x{2040}\x{00C0}-\x{00D6}\x{00D8}-\x{00F6}\x{00F8}-\x{02FF}\x{0370}-\x{037D}\x{037F}-\x{1FFF}\x{200C}-\x{200D}\x{2070}-\x{218F}\x{2C00}-\x{2FEF}\x{3001}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFFD}\x{10000}-\x{EFFFF}])?)';

    private const string LITERAL = '"((?:[^\x22\x5C\x0A\x0D]|\\\\[tbnrf"\'\\\\]|\\\\u[0-9A-Fa-f]{4}|\\\\U[0-9A-Fa-f]{8})*)"(?:\^\^<((?:[^\x00-\x20<>"{}|^`\\\\]|\\\\u[0-9A-Fa-f]{4}|\\\\U[0-9A-Fa-f]{8})*)>|@([A-Za-z]+(?:-[A-Za-z0-9]+)*))?';

    /**
     * Serializes a dataset; every quad becomes one line, statements keep
     * their order.
     */
    public function serialize(Dataset $dataset): string
    {
        $lines = [];

        foreach ($dataset->quads as $quad) {
            $line = self::formatTerm($quad->subject) . ' '
                . self::formatTerm($quad->predicate) . ' '
                . self::formatTerm($quad->object);

            if ($quad->graph !== null) {
                $line .= ' ' . self::formatTerm($quad->graph);
            }

            $lines[] = $line . ' .';
        }

        return $lines === [] ? '' : implode("\n", $lines) . "\n";
    }

    /**
     * Parses N-Quads text into a dataset. Comments and blank lines are
     * ignored.
     *
     * @throws InvalidNQuadsException when a line does not follow the grammar
     */
    public function parse(string $nquads): Dataset
    {
        $quads = [];

        foreach (preg_split('~\r\n|\r|\n~', $nquads) ?: [] as $number => $line) {
            $line = trim($line, " \t");

            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $quads[] = $this->parseLine($line, $number + 1);
        }

        return new Dataset($quads);
    }

    /**
     * @internal
     */
    public static function formatTerm(Iri|BlankNode|Literal $term): string
    {
        if ($term instanceof Iri) {
            return '<' . self::escape($term->value, iri: true) . '>';
        }

        if ($term instanceof BlankNode) {
            return '_:' . $term->id;
        }

        $result = '"' . self::escape($term->value) . '"';

        if ($term->language !== null) {
            return $result . '@' . $term->language;
        }

        if ($term->datatype !== null && $term->datatype !== Literal::XSD_STRING) {
            return $result . '^^<' . self::escape($term->datatype, iri: true) . '>';
        }

        return $result;
    }

    private function parseLine(string $line, int $number): Quad
    {
        $terms = [];
        $offset = 0;
        $length = strlen($line);

        while (count($terms) < 4) {
            $offset += strspn($line, " \t", $offset);

            if ($offset >= $length || $line[$offset] === '.') {
                break;
            }

            $term = $this->matchTerm($line, $offset);

            if ($term === null) {
                throw new InvalidNQuadsException(sprintf('Invalid N-Quads term at line %d', $number));
            }

            $terms[] = $term[0];
            $offset = $term[1];
        }

        $offset += strspn($line, " \t", $offset);

        if (($line[$offset] ?? '') !== '.') {
            throw new InvalidNQuadsException(sprintf('Missing "." at line %d', $number));
        }

        $rest = trim(substr($line, $offset + 1), " \t");

        if ($rest !== '' && $rest[0] !== '#') {
            throw new InvalidNQuadsException(sprintf('Unexpected content after "." at line %d', $number));
        }

        if (count($terms) < 3) {
            throw new InvalidNQuadsException(sprintf('Incomplete statement at line %d', $number));
        }

        [$subject, $predicate, $object] = $terms;
        $graph = $terms[3] ?? null;

        if ((!$subject instanceof Iri && !$subject instanceof BlankNode)
            || !$predicate instanceof Iri
            || ($graph !== null && !$graph instanceof Iri && !$graph instanceof BlankNode)
        ) {
            throw new InvalidNQuadsException(sprintf('Invalid term position at line %d', $number));
        }

        return new Quad($subject, $predicate, $object, $graph);
    }

    /**
     * @return array{0: Iri|BlankNode|Literal, 1: int}|null
     */
    private function matchTerm(string $line, int $offset): ?array
    {
        if (preg_match('~\G' . self::IRIREF . '~u', $line, $match, 0, $offset) === 1) {
            return [new Iri(self::unescape($match[1])), $offset + strlen($match[0])];
        }

        if (preg_match('~\G' . self::BLANK . '~u', $line, $match, 0, $offset) === 1) {
            return [new BlankNode($match[1]), $offset + strlen($match[0])];
        }

        if (preg_match('~\G' . self::LITERAL . '~u', $line, $match, 0, $offset) === 1) {
            $value = self::unescape($match[1]);

            if (isset($match[3]) && $match[3] !== '') {
                $literal = Literal::langString($value, $match[3]);
            } elseif (isset($match[2]) && $match[2] !== '') {
                $literal = Literal::typed($value, self::unescape($match[2]));
            } else {
                $literal = Literal::string($value);
            }

            return [$literal, $offset + strlen($match[0])];
        }

        return null;
    }

    /**
     * Escapes the characters N-Quads canonical form escapes: `\`, `"` and
     * the control characters.
     */
    private static function escape(string $value, bool $iri = false): string
    {
        if ($iri) {
            return $value;
        }

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

    private static function unescape(string $value): string
    {
        return preg_replace_callback(
            '~\\\\(u[0-9A-Fa-f]{4}|U[0-9A-Fa-f]{8}|[tbnrf"\'\\\\])~',
            static function (array $m): string {
                $token = $m[1];

                if ($token[0] === 'u' || $token[0] === 'U') {
                    return mb_chr((int) hexdec(substr($token, 1))) ?: '';
                }

                return match ($token) {
                    't' => "\t",
                    'b' => "\x08",
                    'n' => "\n",
                    'r' => "\r",
                    'f' => "\x0C",
                    default => $token,
                };
            },
            $value,
        ) ?? $value;
    }
}
