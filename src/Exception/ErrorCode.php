<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Exception;

/**
 * Error codes of {@see JsonLdException::errorCode()}.
 *
 * The first group are the error codes of the W3C JSON-LD 1.1 API
 * (section 9.4.2, `JsonLdErrorCode`), plus `recursive context inclusion`,
 * which the W3C test suite still expects. The second group are failures the
 * specification does not name: the limits of this processor, N-Quads syntax
 * and RDF canonicalization.
 *
 * @api
 */
enum ErrorCode: string
{
    case CollidingKeywords = 'colliding keywords';
    case ConflictingIndexes = 'conflicting indexes';
    case ContextOverflow = 'context overflow';
    case CyclicIriMapping = 'cyclic IRI mapping';
    case InvalidIdValue = 'invalid @id value';
    case InvalidImportValue = 'invalid @import value';
    case InvalidIncludedValue = 'invalid @included value';
    case InvalidIndexValue = 'invalid @index value';
    case InvalidNestValue = 'invalid @nest value';
    case InvalidPrefixValue = 'invalid @prefix value';
    case InvalidPropagateValue = 'invalid @propagate value';
    case InvalidProtectedValue = 'invalid @protected value';
    case InvalidReverseValue = 'invalid @reverse value';
    case InvalidVersionValue = 'invalid @version value';
    case InvalidBaseDirection = 'invalid base direction';
    case InvalidBaseIri = 'invalid base IRI';
    case InvalidContainerMapping = 'invalid container mapping';
    case InvalidContextEntry = 'invalid context entry';
    case InvalidContextNullification = 'invalid context nullification';
    case InvalidDefaultLanguage = 'invalid default language';
    case InvalidIriMapping = 'invalid IRI mapping';
    case InvalidJsonLiteral = 'invalid JSON literal';
    case InvalidKeywordAlias = 'invalid keyword alias';
    case InvalidLanguageMapValue = 'invalid language map value';
    case InvalidLanguageMapping = 'invalid language mapping';
    case InvalidLanguageTaggedString = 'invalid language-tagged string';
    case InvalidLanguageTaggedValue = 'invalid language-tagged value';
    case InvalidLocalContext = 'invalid local context';
    case InvalidRemoteContext = 'invalid remote context';
    case InvalidReverseProperty = 'invalid reverse property';
    case InvalidReversePropertyMap = 'invalid reverse property map';
    case InvalidReversePropertyValue = 'invalid reverse property value';
    case InvalidScopedContext = 'invalid scoped context';
    case InvalidScriptElement = 'invalid script element';
    case InvalidSetOrListObject = 'invalid set or list object';
    case InvalidTermDefinition = 'invalid term definition';
    case InvalidTypeMapping = 'invalid type mapping';
    case InvalidTypeValue = 'invalid type value';
    case InvalidTypedValue = 'invalid typed value';
    case InvalidValueObject = 'invalid value object';
    case InvalidValueObjectValue = 'invalid value object value';
    case InvalidVocabMapping = 'invalid vocab mapping';
    case IriConfusedWithPrefix = 'IRI confused with prefix';
    case KeywordRedefinition = 'keyword redefinition';
    case LoadingDocumentFailed = 'loading document failed';
    case LoadingRemoteContextFailed = 'loading remote context failed';
    case MultipleContextLinkHeaders = 'multiple context link headers';
    case ProcessingModeConflict = 'processing mode conflict';
    case ProtectedTermRedefinition = 'protected term redefinition';
    case RecursiveContextInclusion = 'recursive context inclusion';

    // Processor-specific codes, not defined by the W3C JSON-LD 1.1 API.

    /** The document is nested deeper than {@see \Rasuvaeff\JsonLd\ProcessingOptions::$maxDepth}. */
    case DocumentTooDeep = 'document nested too deeply';

    /** An inline JSON string is longer than {@see \Rasuvaeff\JsonLd\ProcessingOptions::$maxBytes}. */
    case DocumentTooLarge = 'document too large';

    /** An N-Quads document does not follow the RDF 1.1 N-Quads grammar. */
    case InvalidNQuads = 'invalid N-Quads';

    /** RDF canonicalization exceeded its work limit (a poison graph). */
    case CanonicalizationLimitExceeded = 'canonicalization limit exceeded';
}
