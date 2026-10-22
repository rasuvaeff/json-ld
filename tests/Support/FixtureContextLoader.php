<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests\Support;

use Rasuvaeff\JsonLd\Exception\DocumentLoadingException;
use Rasuvaeff\JsonLd\Json;
use Rasuvaeff\JsonLd\Loader\ContextLoaderInterface;
use Rasuvaeff\JsonLd\Loader\LoadedContext;

/**
 * Serves W3C test-suite documents from the vendored fixtures directory.
 * Every https://w3c.github.io/json-ld-api/tests/… reference resolves to a
 * local file; anything else is refused, so the conformance suite runs with
 * zero network access.
 */
final readonly class FixtureContextLoader implements ContextLoaderInterface
{
    public const string BASE_IRI = 'https://w3c.github.io/json-ld-api/tests/';

    public function __construct(private string $fixturesDir = __DIR__ . '/../fixtures/w3c') {}

    #[\Override]
    public function load(string $reference): LoadedContext
    {
        $trimmed = ltrim($reference);

        if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
            return new LoadedContext(documentUrl: '', document: Json::decode($reference));
        }

        $path = str_starts_with($reference, self::BASE_IRI)
            ? substr($reference, strlen(self::BASE_IRI))
            : null;

        if ($path === null || !preg_match('~^[A-Za-z0-9._/-]+\z~', $path)) {
            throw new DocumentLoadingException(sprintf('Fixture loader refuses "%s"', $reference));
        }

        $file = $this->fixturesDir . '/' . $path;

        if (!is_file($file)) {
            throw new DocumentLoadingException(sprintf('Fixture "%s" not found', $path));
        }

        $contents = (string) file_get_contents($file);

        if (str_ends_with($file, '.nq')) {
            // N-Quads documents pass through as raw strings; callers parse them.
            return new LoadedContext(documentUrl: $reference, document: [$contents]);
        }

        return new LoadedContext(
            documentUrl: $reference,
            document: Json::decode($contents),
            contextUrl: null,
        );
    }
}
