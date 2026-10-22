<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Loader;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Rasuvaeff\JsonLd\Exception\DocumentLoadingException;
use Rasuvaeff\JsonLd\Exception\InvalidJsonException;
use Rasuvaeff\JsonLd\Json;

/**
 * Loads contexts and documents over HTTP through an injected PSR-18 client.
 *
 * This loader is never the default: the application constructs it
 * explicitly, which is what keeps the processor itself I/O-free. Transport
 * concerns that PSR-18 does not standardize (timeouts, forbidding redirect
 * following, pinning resolved addresses) belong to the injected client; the
 * {@see LoadedContext::$documentUrl} is the requested location, because a
 * plain PSR-18 client does not report where redirects ended.
 *
 * @api
 */
final readonly class PsrContextLoader implements ContextLoaderInterface
{
    private const string ACCEPT = 'application/ld+json, application/json;q=0.9, */*;q=0.1';

    /**
     * @param \Closure(string): bool|null $urlPolicy decides whether a URL may be fetched; null allows every http(s) URL
     * @param int<1, max> $maxBytes response bodies larger than this are rejected
     */
    public function __construct(
        private ClientInterface $client,
        private RequestFactoryInterface $requestFactory,
        private ?\Closure $urlPolicy = null,
        private int $maxBytes = 1_048_576,
    ) {}

    #[\Override]
    public function load(string $reference): LoadedContext
    {
        $scheme = strtolower((string) parse_url($reference, PHP_URL_SCHEME));

        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new DocumentLoadingException(sprintf('Only http and https references can be loaded, "%s" given', $reference));
        }

        if ($this->urlPolicy !== null && !($this->urlPolicy)($reference)) {
            throw new DocumentLoadingException(sprintf('The URL policy refused "%s"', $reference));
        }

        $request = $this->requestFactory
            ->createRequest('GET', $reference)
            ->withHeader('Accept', self::ACCEPT);

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw new DocumentLoadingException($exception->getMessage(), previous: $exception);
        }

        if ($response->getStatusCode() >= 400) {
            throw new DocumentLoadingException(
                sprintf('Loading "%s" failed with HTTP status %d', $reference, $response->getStatusCode()),
            );
        }

        $size = $response->getBody()->getSize();

        if ($size !== null && $size > $this->maxBytes) {
            throw new DocumentLoadingException(sprintf('Response of "%s" exceeds %d bytes', $reference, $this->maxBytes));
        }

        $body = $response->getBody()->read($this->maxBytes + 1);

        if (strlen($body) > $this->maxBytes) {
            throw new DocumentLoadingException(sprintf('Response of "%s" exceeds %d bytes', $reference, $this->maxBytes));
        }

        try {
            return new LoadedContext(documentUrl: $reference, document: Json::decode($body));
        } catch (InvalidJsonException $exception) {
            throw new DocumentLoadingException(
                sprintf('Response of "%s" is not a JSON document', $reference),
                previous: $exception,
            );
        }
    }
}
