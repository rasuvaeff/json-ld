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
    public const string XSD_STRING = 'http://www.w3.org/2001/XMLSchema#string';

    public const string RDF_LANGSTRING = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#langString';

    public const string RDF_JSON = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#JSON';

    public const string RDF_FIRST = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#first';

    public const string RDF_REST = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#rest';

    public const string RDF_NIL = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#nil';

    public const string RDF_TYPE = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type';

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
        return new self($value, self::RDF_LANGSTRING, $language);
    }

    public static function string(string $value): self
    {
        return new self($value, self::XSD_STRING);
    }
}
