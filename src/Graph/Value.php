<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Graph;

/**
 * A value object of a node property (`@value` with its `@language`,
 * `@type` and `@direction`).
 *
 * @api
 */
final readonly class Value
{
    /**
     * @param string|int|float|bool $value the `@value`
     * @param string|null $language the `@language` tag as the document wrote it (tags compare case-insensitively)
     * @param string|null $type the datatype IRI from `@type`, or `@json` for a JSON literal
     * @param string|null $direction `ltr` or `rtl` from `@direction`
     */
    public function __construct(
        public string|int|float|bool $value,
        public ?string $language = null,
        public ?string $type = null,
        public ?string $direction = null,
    ) {}
}
