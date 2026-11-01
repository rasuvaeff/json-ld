<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Rdf;

/**
 * An RDF literal with an optional datatype or language tag.
 *
 * @api
 */
final readonly class Literal
{
    public function __construct(
        public string $value,
        public ?string $datatype = null,
        public ?string $language = null,
    ) {}

    public static function typed(string $value, string $datatype): self
    {
        return new self($value, $datatype);
    }

    public static function langString(string $value, string $language): self
    {
        return new self($value, Vocabulary::RDF_LANGSTRING, $language);
    }

    public static function string(string $value): self
    {
        return new self($value, Vocabulary::XSD_STRING);
    }
}
