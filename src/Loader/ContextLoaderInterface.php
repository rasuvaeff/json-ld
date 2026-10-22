<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Loader;

use Rasuvaeff\JsonLd\Exception\DocumentLoadingException;

/**
 * Loads a context (or a top-level document) referenced by a string.
 *
 * The processor never performs I/O on its own: every string that names
 * something outside of the input document goes through the loader injected
 * by the application. The default loader
 * {@see InlineContextLoader} only parses inline JSON.
 *
 * @api
 */
interface ContextLoaderInterface
{
    /**
     * Loads and decodes the document referenced by `$reference`.
     *
     * @param string $reference inline JSON or a location to resolve
     *
     * @return LoadedContext the decoded document together with its final location
     *
     * @throws DocumentLoadingException when the reference cannot be resolved or decoded
     */
    public function load(string $reference): LoadedContext;
}
