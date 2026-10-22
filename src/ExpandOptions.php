<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd;

use Rasuvaeff\JsonLd\Loader\ContextLoaderInterface;
use Rasuvaeff\JsonLd\Loader\InlineContextLoader;

/**
 * Options for the top-level JSON-LD API operations.
 *
 * @api
 */
final readonly class ExpandOptions
{
    public function __construct(
        /**
         * Base IRI used to resolve relative references before the document
         * provides its own `@base`.
         */
        public ?string $base = null,
        /**
         * Context processed before the document itself, as raw JSON data
         * (decoded) or an inline JSON string.
         */
        public mixed $expandContext = null,
        /**
         * Loader for context references. The default never performs I/O;
         * network access happens only when the application injects a loader
         * that does.
         */
        public ContextLoaderInterface $loader = new InlineContextLoader(),
    ) {}
}
