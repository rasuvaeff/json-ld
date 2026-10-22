<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Context;

use Rasuvaeff\JsonLd\Iri;

/**
 * Lexical forms shared by context processing and expansion.
 *
 * @internal
 */
final class Syntax
{
    /** Characters that let a simple term act as a compact IRI prefix. */
    private const string GEN_DELIMS = ':/?#[]@';

    public static function isAbsoluteIri(string $value): bool
    {
        $colon = strpos($value, ':');

        return $colon !== false
            && $colon > 0
            && self::hasValidScheme(substr($value, 0, $colon))
            && preg_match('~^[^\x00-\x20<>"{}|\\^`]*\z~u', $value) === 1
            && substr_count($value, '#') <= 1
            && preg_match('~%(?![0-9A-Fa-f]{2})~', $value) !== 1;
    }

    public static function isBlankNode(string $value): bool
    {
        return str_starts_with($value, '_:');
    }

    public static function isBlankNodeId(string $value): bool
    {
        return str_starts_with($value, '_:');
    }

    public static function hasValidScheme(string $prefix): bool
    {
        return preg_match('~^[A-Za-z][A-Za-z0-9+.\-]*\z~', $prefix) === 1;
    }

    /** `@` followed by one or more ALPHA, a form reserved for future keywords. */
    public static function looksLikeKeyword(string $value): bool
    {
        return preg_match('~^@[A-Za-z]+\z~', $value) === 1;
    }

    public static function endsWithGenDelim(string $value): bool
    {
        return $value !== '' && str_contains(self::GEN_DELIMS, substr($value, -1));
    }

    public static function resolve(?string $base, string $relative): string
    {
        if ($base === null) {
            return $relative;
        }

        return (string) Iri::of($base)->resolve(Iri::of($relative));
    }
}
