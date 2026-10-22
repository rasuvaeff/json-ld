<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Exception;

/**
 * A context or document could not be loaded: not inline JSON, unreachable
 * location, transport failure, or a non-JSON response.
 *
 * @api
 */
final class DocumentLoadingException extends JsonLdException {}
