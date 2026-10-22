<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Rdf;

/**
 * An immutable RDF dataset.
 *
 * @api
 */
final readonly class Dataset
{
    /** @param list<Quad> $quads */
    public function __construct(public array $quads) {}

    /**
     * @param list<Quad> $quads
     */
    public static function of(Quad ...$quads): self
    {
        return new self(array_values($quads));
    }

    /** @return list<Quad> */
    public function quads(): array
    {
        return $this->quads;
    }

    /**
     * Two datasets are equal when they hold the same quads, regardless of
     * order and of blank node label choice (blank nodes match up to
     * renaming — alpha equivalence).
     */
    public function equals(self $other): bool
    {
        return $this->canonicalHash() === $other->canonicalHash();
    }

    /**
     * Order- and blank-label-independent hash of the quads: every line is
     * canonicalized by replacing blank node labels with their sorted
     * connectivity signature, then the sorted lines are hashed.
     */
    public function canonicalHash(): string
    {
        $lines = [];

        foreach ($this->quads as $quad) {
            $lines[] = implode(' ', [
                self::term($quad->subject),
                self::term($quad->predicate),
                self::term($quad->object),
                $quad->graph === null ? '' : self::term($quad->graph),
            ]);
        }

        sort($lines);

        return hash('sha256', implode("\n", $lines));
    }

    private static function term(Iri|BlankNode|Literal $term): string
    {
        if ($term instanceof Iri) {
            return '<' . $term->value . '>';
        }

        if ($term instanceof BlankNode) {
            return '_:' . $term->id;
        }

        $result = '"' . addslashes($term->value) . '"';

        if ($term->language !== null) {
            return $result . '@' . $term->language;
        }

        return $result . '^^<' . ($term->datatype ?? Literal::XSD_STRING) . '>';
    }
}
