<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Exception;

/**
 * A string given as JSON turned out not to be decodable JSON, or decoded to
 * something other than an object or an array.
 *
 * @api
 */
final class InvalidJsonException extends JsonLdException {}
