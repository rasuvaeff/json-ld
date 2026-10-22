<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Rdf;

/**
 * JSON Canonicalization Scheme (RFC 8785) for JSON literals.
 *
 * @internal
 */
final class JsonCanonicalizer
{
    public static function serialize(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return self::number($value);
        }

        if (is_string($value)) {
            return self::string($value);
        }

        if (!is_array($value)) {
            return 'null';
        }

        if (array_is_list($value)) {
            return '[' . implode(',', array_map(self::serialize(...), $value)) . ']';
        }

        $keys = array_map(strval(...), array_keys($value));
        usort(
            $keys,
            static fn(string $left, string $right): int => strcmp(
                mb_convert_encoding($left, 'UTF-16BE', 'UTF-8'),
                mb_convert_encoding($right, 'UTF-16BE', 'UTF-8'),
            ),
        );

        $members = [];

        foreach ($keys as $key) {
            $members[] = self::string($key) . ':' . self::serialize($value[$key]);
        }

        return '{' . implode(',', $members) . '}';
    }

    private static function string(string $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_INVALID_UTF8_SUBSTITUTE);

        return $encoded === false ? '""' : str_replace('\\u007f', "\x7F", $encoded);
    }

    /**
     * ECMAScript Number-to-String (ECMA-262 7.1.12.1) from the shortest
     * round-trip digits.
     */
    private static function number(float $value): string
    {
        if ($value === 0.0) {
            return '0';
        }

        $sign = $value < 0 ? '-' : '';
        $shortest = (string) json_encode(abs($value), JSON_PRESERVE_ZERO_FRACTION);

        if (preg_match('~^(\d+)(?:\.(\d+))?(?:[eE]([+-]?\d+))?\z~', $shortest, $match) !== 1) {
            return $sign . $shortest;
        }

        $integer = $match[1];
        $fraction = $match[2] ?? '';
        $exponent = (int) ($match[3] ?? 0);

        $digits = $integer . $fraction;
        $pointPosition = strlen($integer) + $exponent;

        $trimmedLeading = ltrim($digits, '0');
        $pointPosition -= strlen($digits) - strlen($trimmedLeading);
        $digits = rtrim($trimmedLeading, '0');

        $k = strlen($digits);
        $n = $pointPosition;

        if ($k <= $n && $n <= 21) {
            return $sign . $digits . str_repeat('0', $n - $k);
        }

        if (0 < $n && $n <= 21) {
            return $sign . substr($digits, 0, $n) . '.' . substr($digits, $n);
        }

        if (-6 < $n && $n <= 0) {
            return $sign . '0.' . str_repeat('0', -$n) . $digits;
        }

        $exponentPart = ($n - 1 < 0 ? '-' : '+') . abs($n - 1);

        if ($k === 1) {
            return $sign . $digits . 'e' . $exponentPart;
        }

        return $sign . $digits[0] . '.' . substr($digits, 1) . 'e' . $exponentPart;
    }
}
