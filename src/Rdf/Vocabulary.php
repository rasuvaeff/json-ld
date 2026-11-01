<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Rdf;

/**
 * IRIs of the RDF and XML Schema terms the JSON-LD to RDF conversion uses.
 *
 * @api
 */
final class Vocabulary
{
    public const string RDF = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';

    public const string XSD = 'http://www.w3.org/2001/XMLSchema#';

    public const string I18N = 'https://www.w3.org/ns/i18n#';

    public const string RDF_TYPE = self::RDF . 'type';

    public const string RDF_FIRST = self::RDF . 'first';

    public const string RDF_REST = self::RDF . 'rest';

    public const string RDF_NIL = self::RDF . 'nil';

    public const string RDF_LIST = self::RDF . 'List';

    public const string RDF_LANGSTRING = self::RDF . 'langString';

    public const string RDF_JSON = self::RDF . 'JSON';

    public const string RDF_VALUE = self::RDF . 'value';

    public const string RDF_LANGUAGE = self::RDF . 'language';

    public const string RDF_DIRECTION = self::RDF . 'direction';

    public const string XSD_STRING = self::XSD . 'string';

    public const string XSD_BOOLEAN = self::XSD . 'boolean';

    public const string XSD_INTEGER = self::XSD . 'integer';

    public const string XSD_DOUBLE = self::XSD . 'double';

    private function __construct() {}
}
