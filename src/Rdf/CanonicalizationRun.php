<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Rdf;

use Rasuvaeff\JsonLd\Exception\CanonicalizationLimitException;

/**
 * One run of the RDF Dataset Canonicalization algorithm, RDFC-1.0
 * section 4.4, with its canonicalization state: the blank node to quads
 * map, the canonical issuer and the work counters guarding against poison
 * graphs (section 4.4.1, "Hash N-Degree Quads" calls per blank node).
 *
 * Blank nodes are identified by their label with the `_:` prefix.
 *
 * @internal
 */
final class CanonicalizationRun
{
    /** @var array<string, array<string, Quad>> label => quads mentioning it, keyed by their statement */
    private array $quads = [];

    /** @var array<string, string> */
    private array $firstDegree = [];

    /** @var array<string, int> */
    private array $deepIterations = [];

    private readonly IdentifierIssuer $canonical;

    private int $maxDeepIterations = 0;

    /**
     * @param int $workFactor Hash N-Degree Quads may run `$workFactor` times the number of blank nodes
     *                        without a unique first-degree hash, for each blank node
     */
    public function __construct(
        private readonly HashAlgorithm $algorithm,
        private readonly int $workFactor,
    ) {
        $this->canonical = new IdentifierIssuer('c14n');
    }

    /**
     * @throws CanonicalizationLimitException
     */
    public function canonicalize(Dataset $dataset): CanonicalForm
    {
        /** @var array<string, Quad> $statements */
        $statements = [];

        foreach ($dataset->quads as $quad) {
            $statement = NQuadsWriter::statement($quad);
            $statements[$statement] = $quad;

            foreach ([$quad->subject, $quad->object, $quad->graph] as $term) {
                if ($term instanceof BlankNode) {
                    $this->quads['_:' . $term->id][$statement] = $quad;
                }
            }
        }

        /** @var array<string, list<string>> $byHash */
        $byHash = [];

        foreach (array_keys($this->quads) as $label) {
            $byHash[$this->hashFirstDegree($label)][] = $label;
        }

        ksort($byHash, SORT_STRING);

        foreach ($byHash as $hash => $labels) {
            if (count($labels) === 1) {
                $this->canonical->issue($labels[0]);
                unset($byHash[$hash]);
            }
        }

        $this->maxDeepIterations = $this->workFactor * array_sum(array_map(count(...), $byHash));

        foreach ($byHash as $labels) {
            /** @var list<array{0: string, 1: IdentifierIssuer}> $paths */
            $paths = [];

            foreach ($labels as $label) {
                if ($this->canonical->issued($label) !== null) {
                    continue;
                }

                $issuer = new IdentifierIssuer('b');
                $issuer->issue($label);
                $paths[] = $this->hashNDegree($label, $issuer);
            }

            usort(
                $paths,
                /**
                 * @param array{0: string, 1: IdentifierIssuer} $left
                 * @param array{0: string, 1: IdentifierIssuer} $right
                 */
                static fn(array $left, array $right): int => strcmp($left[0], $right[0]),
            );

            foreach ($paths as [, $issuer]) {
                foreach (array_keys($issuer->all()) as $label) {
                    $this->canonical->issue($label);
                }
            }
        }

        $issued = $this->canonical->all();
        $lines = [];

        foreach ($statements as $quad) {
            $lines[] = NQuadsWriter::statement(
                $quad,
                static fn(BlankNode $node): string => substr($issued['_:' . $node->id] ?? '', 2),
            ) . "\n";
        }

        sort($lines, SORT_STRING);

        return new CanonicalForm(implode('', $lines), $issued, $this->algorithm);
    }

    /**
     * Hash First Degree Quads, section 4.6.
     */
    private function hashFirstDegree(string $label): string
    {
        if (isset($this->firstDegree[$label])) {
            return $this->firstDegree[$label];
        }

        $lines = [];

        foreach ($this->quads[$label] as $quad) {
            $lines[] = NQuadsWriter::statement(
                $quad,
                static fn(BlankNode $node): string => ('_:' . $node->id) === $label ? 'a' : 'z',
            ) . "\n";
        }

        sort($lines, SORT_STRING);

        return $this->firstDegree[$label] = $this->algorithm->hash(implode('', $lines));
    }

    /**
     * Hash Related Blank Node, section 4.7.
     */
    private function hashRelated(string $related, Quad $quad, IdentifierIssuer $issuer, string $position): string
    {
        $input = $position;

        if ($position !== 'g') {
            $input .= '<' . $quad->predicate->value . '>';
        }

        $input .= $this->canonical->issued($related) ?? $issuer->issued($related) ?? $this->hashFirstDegree($related);

        return $this->algorithm->hash($input);
    }

    /**
     * Hash N-Degree Quads, section 4.8.
     *
     * @return array{0: string, 1: IdentifierIssuer}
     *
     * @throws CanonicalizationLimitException
     */
    private function hashNDegree(string $label, IdentifierIssuer $issuer): array
    {
        $iterations = ($this->deepIterations[$label] ?? 0) + 1;

        if ($iterations > $this->maxDeepIterations) {
            throw new CanonicalizationLimitException($this->maxDeepIterations);
        }

        $this->deepIterations[$label] = $iterations;

        /** @var array<string, list<string>> $related */
        $related = [];

        foreach ($this->quads[$label] as $quad) {
            foreach (['s' => $quad->subject, 'o' => $quad->object, 'g' => $quad->graph] as $position => $term) {
                if ($term instanceof BlankNode && '_:' . $term->id !== $label) {
                    $node = '_:' . $term->id;
                    $related[$this->hashRelated($node, $quad, $issuer, $position)][] = $node;
                }
            }
        }

        ksort($related, SORT_STRING);
        $data = '';

        foreach ($related as $hash => $nodes) {
            $data .= $hash;
            $chosenPath = '';
            $chosenIssuer = $issuer;

            foreach (self::permutations($nodes) as $permutation) {
                $copy = clone $issuer;
                $path = '';
                $recursion = [];

                foreach ($permutation as $node) {
                    $canonical = $this->canonical->issued($node);

                    if ($canonical !== null) {
                        $path .= $canonical;
                    } else {
                        if ($copy->issued($node) === null) {
                            $recursion[] = $node;
                        }

                        $path .= $copy->issue($node);
                    }

                    if ($this->worse($path, $chosenPath)) {
                        continue 2;
                    }
                }

                foreach ($recursion as $node) {
                    [$result, $resultIssuer] = $this->hashNDegree($node, $copy);
                    $path .= $copy->issue($node) . '<' . $result . '>';
                    $copy = $resultIssuer;

                    if ($this->worse($path, $chosenPath)) {
                        continue 2;
                    }
                }

                if ($chosenPath === '' || strcmp($path, $chosenPath) < 0) {
                    $chosenPath = $path;
                    $chosenIssuer = $copy;
                }
            }

            $data .= $chosenPath;
            $issuer = $chosenIssuer;
        }

        return [$this->algorithm->hash($data), $issuer];
    }

    /**
     * A path that is already longer than or as long as the chosen one and
     * sorts after it can never be chosen.
     */
    private function worse(string $path, string $chosen): bool
    {
        return $chosen !== '' && strlen($path) >= strlen($chosen) && strcmp($path, $chosen) > 0;
    }

    /**
     * @param list<string> $items
     *
     * @return \Generator<int, list<string>>
     */
    private static function permutations(array $items): \Generator
    {
        if (count($items) <= 1) {
            yield $items;

            return;
        }

        foreach ($items as $index => $item) {
            $rest = $items;
            unset($rest[$index]);

            foreach (self::permutations(array_values($rest)) as $permutation) {
                yield [$item, ...$permutation];
            }
        }
    }
}
