<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Loader;

use Rasuvaeff\JsonLd\Exception\DocumentLoadingException;
use Rasuvaeff\JsonLd\Exception\ErrorCode;

/**
 * Serves documents from a fixed map of URL to decoded document, without
 * I/O: the usual way to answer well-known contexts (`https://schema.org/`)
 * from memory and refuse everything else.
 *
 * URLs match exactly: `https://schema.org` and `https://schema.org/` are
 * two keys.
 *
 * @api
 */
final readonly class MapContextLoader implements ContextLoaderInterface
{
    /**
     * @param array<string, array<string|int, mixed>> $documents URL => decoded document (object or array at the top level)
     */
    public function __construct(private array $documents) {}

    /**
     * @throws DocumentLoadingException when the URL is not in the map
     */
    #[\Override]
    public function load(string $reference): LoadedContext
    {
        if (!array_key_exists($reference, $this->documents)) {
            throw new DocumentLoadingException(
                ErrorCode::LoadingDocumentFailed,
                sprintf('"%s" is not in the map of known documents', mb_strimwidth($reference, 0, 60, '…')),
            );
        }

        return new LoadedContext(documentUrl: $reference, document: $this->documents[$reference]);
    }
}
