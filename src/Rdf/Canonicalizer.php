<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Rdf;

/**
 * Computes a canonical text form of a dataset in which blank node labels
 * are replaced by labels derived from the structure of the graph, so that
 * two datasets have the same text exactly when they are isomorphic (equal
 * up to renaming of blank nodes).
 *
 * Colour refinement splits the blank nodes by their neighbourhood; classes
 * that stay tied (symmetric nodes) are broken by trying each member and
 * keeping the smallest result.
 *
 * @internal
 */
final class Canonicalizer
{
    public static function canonicalize(Dataset $dataset): string
    {
        $labels = [];
        $rows = [];

        foreach ($dataset->quads as $quad) {
            $row = [$quad->subject, $quad->predicate, $quad->object, $quad->graph];
            $rows[] = $row;

            foreach ($row as $term) {
                if ($term instanceof BlankNode) {
                    $labels[$term->id] = '';
                }
            }
        }

        $colors = array_fill_keys(array_keys($labels), '');

        return self::resolve($rows, $colors);
    }

    /**
     * @param list<array{0: Iri|BlankNode, 1: Iri, 2: Iri|BlankNode|Literal, 3: Iri|BlankNode|null}> $rows
     * @param array<string, string> $colors
     */
    private static function resolve(array $rows, array $colors): string
    {
        $colors = self::refine($rows, $colors);

        $classes = [];

        foreach ($colors as $label => $color) {
            $classes[$color][] = strval($label);
        }

        $tied = array_filter($classes, static fn(array $members): bool => count($members) > 1);

        if ($tied === []) {
            $ordered = array_keys($classes);
            sort($ordered, SORT_STRING);
            $names = [];

            foreach ($ordered as $position => $color) {
                $names[$classes[$color][0]] = 'c' . $position;
            }

            return self::serialize($rows, $names);
        }

        ksort($tied, SORT_STRING);
        uasort($tied, static fn(array $left, array $right): int => count($left) <=> count($right));
        $members = $tied[array_key_first($tied)];

        $candidates = [];

        foreach ($members as $member) {
            $branch = $colors;
            $branch[$member] = hash('sha256', $colors[$member] . '|individualized');
            $candidates[] = self::resolve($rows, $branch);
        }

        sort($candidates, SORT_STRING);

        return $candidates[0];
    }

    /**
     * @param list<array{0: Iri|BlankNode, 1: Iri, 2: Iri|BlankNode|Literal, 3: Iri|BlankNode|null}> $rows
     * @param array<string, string> $colors
     *
     * @return array<string, string>
     */
    private static function refine(array $rows, array $colors): array
    {
        $classCount = count(array_unique($colors));
        $counter = count($colors);

        for ($round = 0; $round <= $counter; $round++) {
            $signatures = array_fill_keys(array_keys($colors), []);

            foreach ($rows as $row) {
                foreach ($row as $position => $term) {
                    if (!$term instanceof BlankNode) {
                        continue;
                    }

                    $parts = [];

                    foreach ($row as $otherPosition => $other) {
                        $parts[] = $other instanceof BlankNode
                            ? ($otherPosition === $position ? 'self' : $colors[$other->id])
                            : ($other === null ? '' : NQuads::formatTerm($other));
                    }

                    $signatures[$term->id][] = $position . '|' . implode('|', $parts);
                }
            }

            $next = [];

            foreach ($signatures as $label => $lines) {
                sort($lines, SORT_STRING);
                $next[$label] = hash('sha256', $colors[$label] . "\n" . implode("\n", $lines));
            }

            $count = count(array_unique($next));
            $colors = $next;

            if ($count === $classCount) {
                break;
            }

            $classCount = $count;
        }

        return $colors;
    }

    /**
     * @param list<array{0: Iri|BlankNode, 1: Iri, 2: Iri|BlankNode|Literal, 3: Iri|BlankNode|null}> $rows
     * @param array<string, string> $names
     */
    private static function serialize(array $rows, array $names): string
    {
        $lines = [];

        foreach ($rows as $row) {
            $parts = [];

            foreach ($row as $term) {
                if ($term === null) {
                    $parts[] = '';
                } elseif ($term instanceof BlankNode) {
                    $parts[] = '_:' . $names[$term->id];
                } else {
                    $parts[] = NQuads::formatTerm($term);
                }
            }

            $lines[] = implode(' ', $parts);
        }

        sort($lines, SORT_STRING);

        return implode("\n", array_unique($lines));
    }
}
