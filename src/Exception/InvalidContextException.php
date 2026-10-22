<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Exception;

/**
 * A `@context` value is syntactically or structurally invalid: wrong type,
 * unknown keyword, or a member violating the JSON-LD 1.1 context grammar.
 *
 * @api
 */
final class InvalidContextException extends JsonLdException {}
