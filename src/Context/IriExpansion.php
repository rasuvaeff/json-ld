<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Context;

use Rasuvaeff\JsonLd\Iri;
use Rasuvaeff\JsonLd\Keywords;

/**
 * IRI expansion against a processed active context, JSON-LD 1.1 API
 * section 5.1.2 (the variant used during expansion, without lazy term
 * creation; the context processor has its own lazy variant).
 *
 * @internal
 */
final class IriExpansion
{
    /**
     * Expands a value into an IRI (or a keyword alias).
     *
     * @param bool $vocab concatenate the vocabulary mapping when the value is a term
     * @param bool $documentRelative resolve a relative result against the base
     *
     * @return string|null absolute IRI, blank node identifier, keyword, or null when nothing can be expanded
     */
    public static function expand(ActiveContext $context, string $value, bool $vocab = false, bool $documentRelative = false): ?string
    {
        if ($value === '') {
            return null;
        }

        if (Keywords::isKeyword($value)) {
            return $value;
        }

        $definition = $context->term($value);

        if ($definition !== null) {
            return $definition->keyword ?? $definition->iriMapping;
        }

        if ($context->isMapped($value)) {
            return null;
        }

        $colon = strpos($value, ':');

        if ($colon !== false) {
            $prefix = substr($value, 0, $colon);

            if ($prefix === '_') {
                return $value;
            }

            $prefixDefinition = $context->term($prefix);

            if ($prefixDefinition !== null && $prefixDefinition->prefix && $prefixDefinition->iriMapping !== null) {
                return $prefixDefinition->iriMapping . substr($value, $colon + 1);
            }

            if (self::hasValidScheme($prefix)) {
                // An absolute IRI is returned as is, without dot-segment
                // removal (JSON-LD IRI expansion, not RFC merge).
                return $value;
            }
        }

        if ($vocab && $context->vocab !== null) {
            return $context->vocab . $value;
        }

        if ($documentRelative) {
            // Without a base, a relative reference stays as it is.
            if ($context->base !== null) {
                return (string) $context->base->resolve(Iri::of($value));
            }

            return $value;
        }

        return null;
    }

    /**
     * A value is an absolute IRI when it has a syntactically valid scheme;
     * blank node identifiers are handled by the caller.
     */
    public static function isAbsolute(string $value): bool
    {
        $colon = strpos($value, ':');

        // Whitespace makes it invalid as an IRI reference (RFC 3986/3987).
        return $colon !== false
            && $colon > 0
            && self::hasValidScheme(substr($value, 0, $colon))
            && preg_match('~\S~', $value) === 1
            && preg_match('~\s~', $value) !== 1;
    }

    /**
     * RFC 3986 scheme: ALPHA *( ALPHA / DIGIT / "+" / "-" / "." ).
     */
    public static function hasValidScheme(string $prefix): bool
    {
        return $prefix !== '' && preg_match('~^[A-Za-z][A-Za-z0-9+.\-]*\z~', $prefix) === 1;
    }
}
