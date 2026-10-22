<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd;

use JsonException;
use Rasuvaeff\JsonLd\Exception\InvalidJsonException;

/**
 * JSON encoding and decoding for JSON-LD documents. Every decoded document
 * is represented as associative arrays (never {@see \stdClass}) so that the
 * whole processor stays psalm-friendly.
 *
 * @psalm-type JsonValue = null|bool|int|float|string|array<array-key, mixed>
 *
 * @internal
 */
final class Json
{
    /**
     * Decodes a JSON document that must be an object or an array at the top
     * level, as required for JSON-LD documents and contexts.
     *
     * @return array<string|int, mixed> decoded document as an associative array or a list
     *
     * @throws InvalidJsonException when the string is not valid JSON or is not an object/array
     */
    public static function decode(string $json): array
    {
        try {
            $decoded = json_decode(json: $json, associative: true, depth: 512, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidJsonException($exception->getMessage(), previous: $exception);
        }

        if (!is_array($decoded)) {
            throw new InvalidJsonException('JSON document must be an object or an array');
        }

        return $decoded;
    }

    /**
     * Encodes a value as JSON, keeping unicode characters and slashes intact.
     *
     * @param mixed $value
     */
    public static function encode($value, bool $pretty = false): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        try {
            return json_encode($value, $flags | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidJsonException($exception->getMessage(), previous: $exception);
        }
    }
}
