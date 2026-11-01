<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd;

use JsonException;
use Rasuvaeff\JsonLd\Exception\ErrorCode;
use Rasuvaeff\JsonLd\Exception\InvalidInputException;
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
     * @param int<1, 2147483646> $maxDepth deepest nesting of arrays and objects accepted
     * @param int<1, max>|null $maxBytes longest accepted input, null for no limit
     *
     * @return array<string|int, mixed> decoded document as an associative array or a list
     *
     * @throws InvalidJsonException when the string is not valid JSON or is not an object/array
     * @throws InvalidInputException when the input exceeds one of the limits
     */
    public static function decode(string $json, int $maxDepth = ProcessingOptions::DEFAULT_MAX_DEPTH, ?int $maxBytes = null): array
    {
        if ($maxBytes !== null && strlen($json) > $maxBytes) {
            throw new InvalidInputException(
                ErrorCode::DocumentTooLarge,
                sprintf('JSON document of %d bytes exceeds the limit of %d bytes', strlen($json), $maxBytes),
            );
        }

        try {
            // json_decode counts the scalar level too: depth N admits N - 1 levels of arrays
            $decoded = json_decode(json: $json, associative: true, depth: $maxDepth + 1, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            if ($exception->getCode() === JSON_ERROR_DEPTH) {
                throw new InvalidInputException(ErrorCode::DocumentTooDeep, previous: $exception);
            }

            throw new InvalidJsonException(ErrorCode::LoadingDocumentFailed, $exception->getMessage(), $exception);
        }

        if (!is_array($decoded)) {
            throw new InvalidJsonException(ErrorCode::LoadingDocumentFailed, 'JSON document must be an object or an array');
        }

        return $decoded;
    }

    /**
     * Encodes a value as JSON, keeping unicode characters and slashes intact.
     *
     * @throws JsonException when the value cannot be encoded
     */
    public static function encode(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
