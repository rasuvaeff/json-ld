<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd;

/**
 * How the base direction of a string (`@direction`) is carried in RDF,
 * the `rdfDirection` option of JSON-LD 1.1 API section 9.
 *
 * @api
 */
enum RdfDirection: string
{
    /** The direction goes into the datatype IRI: `https://www.w3.org/ns/i18n#en-us_rtl`. */
    case I18nDatatype = 'i18n-datatype';

    /** The value becomes a blank node with `rdf:value`, `rdf:language` and `rdf:direction`. */
    case CompoundLiteral = 'compound-literal';
}
