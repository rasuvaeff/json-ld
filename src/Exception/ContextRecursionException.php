<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Exception;

/**
 * Contexts include each other in a cycle, or the nesting exceeds the
 * processor's limit (W3C JSON-LD 1.1 API, "Context Processing" step 3.4).
 *
 * @api
 */
final class ContextRecursionException extends JsonLdException {}
