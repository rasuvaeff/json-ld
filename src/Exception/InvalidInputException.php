<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Exception;

/**
 * A document violates the JSON-LD 1.1 grammar (for example an invalid
 * `@id`, `@type` or value object); the message is the W3C error code.
 *
 * @api
 */
final class InvalidInputException extends JsonLdException {}
