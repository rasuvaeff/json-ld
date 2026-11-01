<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Loader;

use Rasuvaeff\JsonLd\Exception\DocumentLoadingException;
use Rasuvaeff\JsonLd\Exception\ErrorCode;
use Rasuvaeff\JsonLd\Exception\InvalidInputException;
use Rasuvaeff\JsonLd\Exception\InvalidJsonException;
use Rasuvaeff\JsonLd\Json;

/**
 * The default loader: parses inline JSON and nothing else. It never touches
 * the network or the filesystem, so the processor cannot perform I/O unless
 * the application explicitly configures a loader that does.
 *
 * @api
 */
final readonly class InlineContextLoader implements ContextLoaderInterface
{
    #[\Override]
    public function load(string $reference): LoadedContext
    {
        $trimmed = ltrim($reference);

        if ($trimmed === '' || ($trimmed[0] !== '{' && $trimmed[0] !== '[')) {
            throw new DocumentLoadingException(
                ErrorCode::LoadingDocumentFailed,
                sprintf(
                    '"%s" is not inline JSON; loading remote documents requires an explicitly configured loader',
                    mb_strimwidth($reference, 0, 60, '…'),
                ),
            );
        }

        try {
            return new LoadedContext(documentUrl: '', document: Json::decode($reference));
        } catch (InvalidJsonException|InvalidInputException $exception) {
            throw new DocumentLoadingException(ErrorCode::LoadingDocumentFailed, $exception->getMessage(), $exception);
        }
    }
}
