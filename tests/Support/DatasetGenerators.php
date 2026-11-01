<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests\Support;

use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Gen;

/**
 * Generators of small RDF datasets as N-Quads rows over the blank nodes
 * `_:n0`…`_:n4`: random rows, and symmetric shapes (cycles) whose blank
 * nodes share their first-degree hash, so that canonicalization has to
 * take the Hash N-Degree Quads path.
 */
final readonly class DatasetGenerators
{
    public const array LABELS = ['n0', 'n1', 'n2', 'n3', 'n4'];

    /**
     * @return ArbitraryInterface<array{kind: string, rows: list<array{string, string, string, string}>}>
     */
    public static function dataset(): ArbitraryInterface
    {
        return Gen::frequency([
            [3, Gen::map(Gen::arrayOf(self::row(), minSize: 1, maxSize: 8), static fn(array $rows): array => ['kind' => 'random', 'rows' => $rows])],
            [1, Gen::map(Gen::intBetween(2, 5), static fn(int $size): array => ['kind' => 'cycle', 'rows' => self::cycle($size)])],
        ]);
    }

    public static function seed(): ArbitraryInterface
    {
        return Gen::intBetween(0, 1_000_000);
    }

    /**
     * @param list<array{string, string, string, string}> $rows
     * @param array<string, string> $labels blank node label => replacement
     */
    public static function nquads(array $rows, array $labels = []): string
    {
        $text = '';

        foreach ($rows as $row) {
            $terms = array_map(
                static fn(string $term): string => str_starts_with($term, '_:') ? '_:' . ($labels[substr($term, 2)] ?? substr($term, 2)) : $term,
                array_values(array_filter($row, static fn(string $term): bool => $term !== '')),
            );
            $text .= implode(' ', $terms) . " .\n";
        }

        return $text;
    }

    private static function row(): ArbitraryInterface
    {
        $blank = Gen::elements(array_map(static fn(string $label): string => '_:' . $label, self::LABELS));
        $iri = Gen::elements(['<http://e/a>', '<http://e/b>']);

        return Gen::tuple(
            Gen::frequency([[3, $blank], [1, $iri]]),
            Gen::elements(['<http://p>', '<http://q>']),
            Gen::frequency([[3, $blank], [1, $iri], [1, Gen::elements(['"x"', '"y"@en', '"1"^^<http://www.w3.org/2001/XMLSchema#integer>'])]]),
            Gen::frequency([[3, Gen::constant('')], [1, Gen::elements(['<http://g>', '_:n0', '_:n4'])]]),
        );
    }

    /**
     * @return list<array{string, string, string, string}>
     */
    private static function cycle(int $size): array
    {
        $rows = [];

        for ($index = 0; $index < $size; $index++) {
            $rows[] = ['_:n' . $index, '<http://p>', '_:n' . (($index + 1) % $size), ''];
        }

        return $rows;
    }
}
