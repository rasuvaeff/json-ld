<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Rdf;

use Rasuvaeff\JsonLd\Exception\InvalidJsonException;

/**
 * N-Quads serialization and parsing, RDF 1.1 N-Quads.
 *
 * @api
 */
final class NQuads
{
    /**
     * Serializes a dataset; every quad becomes one line, statements keep
     * their order.
     */
    public function serialize(Dataset $dataset): string
    {
        $lines = [];

        foreach ($dataset->quads as $quad) {
            $line = self::term($quad->subject) . ' '
                . self::term($quad->predicate) . ' '
                . self::term($quad->object);

            if ($quad->graph !== null) {
                $line .= ' ' . self::term($quad->graph);
            }

            $lines[] = $line . ' .';
        }

        return $lines === [] ? '' : implode("\n", $lines) . "\n";
    }

    /**
     * Parses N-Quads text into a dataset.
     *
     * @throws InvalidJsonException never; invalid lines throw {@see \InvalidArgumentException}
     */
    public function parse(string $nquads): Dataset
    {
        $quads = [];

        foreach (preg_split('~\r\n|\r|\n~', $nquads, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $quads[] = $this->parseLine($line);
        }

        return new Dataset($quads);
    }

    private function parseLine(string $line): Quad
    {
        $term = '(<(?:[^>\\\\]|\\\\.)*>|_:[A-Za-z0-9_\\-.]+|"(?:[^"\\\\]|\\\\.)*"(?:\\^\\^<(?:[^>\\\\]|\\\\.)*>|@[A-Za-z]+(?:-[A-Za-z0-9]+)*)?)';

        if (preg_match("~^{$term}\\s+{$term}\\s+{$term}(?:\\s+{$term})?\\s*\\.\\z~", $line, $m) !== 1) {
            throw new \InvalidArgumentException(sprintf('Invalid N-Quads line: %s', $line));
        }

        $subject = $this->parseTerm($m[1]);
        $predicate = $this->parseTerm($m[2]);
        $object = $this->parseTerm($m[3]);

        if (!$subject instanceof Iri && !$subject instanceof BlankNode) {
            throw new \InvalidArgumentException(sprintf('Invalid subject in N-Quads line: %s', $line));
        }

        if (!$predicate instanceof Iri) {
            throw new \InvalidArgumentException(sprintf('Invalid predicate in N-Quads line: %s', $line));
        }

        $graph = isset($m[4]) && $m[4] !== '' ? $this->parseTerm($m[4]) : null;

        if ($graph !== null && !$graph instanceof Iri && !$graph instanceof BlankNode) {
            throw new \InvalidArgumentException(sprintf('Invalid graph name in N-Quads line: %s', $line));
        }

        return new Quad($subject, $predicate, $object, $graph);
    }

    private function parseTerm(string $raw): Iri|BlankNode|Literal
    {
        if (str_starts_with($raw, '_:')) {
            return new BlankNode(substr($raw, 2));
        }

        if (str_starts_with($raw, '<')) {
            return new Iri(self::unescape(substr($raw, 1, -1)));
        }

        if (preg_match('~^"((?:[^"\\\\]|\\\\.)*)"(?:\\^\\^<((?:[^>\\\\]|\\\\.)*)>|@([A-Za-z]+(?:-[A-Za-z0-9]+)*))?$~', $raw, $m) !== 1) {
            throw new \InvalidArgumentException(sprintf('Invalid N-Quads term: %s', $raw));
        }

        $value = self::unescape($m[1]);

        if (isset($m[3]) && $m[3] !== '') {
            return Literal::langString($value, $m[3]);
        }

        if (isset($m[2]) && $m[2] !== '') {
            return Literal::typed($value, self::unescape($m[2]));
        }

        return new Literal($value);
    }

    private static function term(Iri|BlankNode|Literal $term): string
    {
        if ($term instanceof Iri) {
            return '<' . self::escape($term->value) . '>';
        }

        if ($term instanceof BlankNode) {
            return '_:' . $term->id;
        }

        $result = '"' . self::escape($term->value) . '"';

        if ($term->language !== null) {
            return $result . '@' . $term->language;
        }

        if ($term->datatype !== null && $term->datatype !== Literal::XSD_STRING) {
            return $result . '^^<' . self::escape($term->datatype) . '>';
        }

        return $result;
    }

    /**
     * Escapes STRING_LITERAL_QUOTE specials: quote, backslash and the
     * characters N-Quads requires in ECHAR/UCHAR form.
     */
    private static function escape(string $value): string
    {
        $escaped = str_replace(
            ['\\', '"', "\n", "\r", "\t"],
            ['\\\\', '\\"', '\\n', '\\r', '\\t'],
            $value,
        );

        if (preg_match('~[^\x20-\x7E]~u', $escaped) === 1 || preg_match('~[\x00-\x1F]~', $escaped) === 1) {
            $escaped = preg_replace_callback(
                '~[\x00-\x1F]|[\x7F]~',
                static fn(array $m): string => sprintf('\\u%04X', ord($m[0])),
                $escaped,
            ) ?? $escaped;
        }

        return $escaped;
    }

    private static function unescape(string $value): string
    {
        return preg_replace_callback(
            '~\\\\(u[0-9A-Fa-f]{4}|U[0-9A-Fa-f]{8}|[tbnrf"\'\\\\])~',
            static function (array $m): string {
                $token = $m[1];

                if ($token[0] === 'u' || $token[0] === 'U') {
                    return mb_chr((int) hexdec(substr($token, 1)));
                }

                return match ($token) {
                    't' => "\t",
                    'b' => "\x08",
                    'n' => "\n",
                    'r' => "\r",
                    'f' => "\x0C",
                    '"', "'", '\\' => $token,
                    default => $token,
                };
            },
            $value,
        ) ?? $value;
    }
}
