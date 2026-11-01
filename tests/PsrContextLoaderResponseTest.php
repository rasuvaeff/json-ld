<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Exception\DocumentLoadingException;
use Rasuvaeff\JsonLd\Exception\ErrorCode;
use Rasuvaeff\JsonLd\Json;
use Rasuvaeff\JsonLd\Loader\LoadedContext;
use Rasuvaeff\JsonLd\Loader\PsrContextLoader;
use Rasuvaeff\JsonLd\Tests\Support\StringStream;
use Rasuvaeff\JsonLd\Tests\Support\StubHttpClient;
use Rasuvaeff\JsonLd\Tests\Support\StubMessage;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(PsrContextLoader::class)]
#[Covers(LoadedContext::class)]
#[Covers(Json::class)]
final class PsrContextLoaderResponseTest
{
    public function loadsAJsonResponseAndAsksForJsonLd(): void
    {
        $client = new StubHttpClient(new StubMessage(200, new StringStream('{"@context": {"a": "http://x/a"}}')));
        $loaded = (new PsrContextLoader($client, $client))->load('https://example.com/ctx');

        Assert::same($loaded->documentUrl, 'https://example.com/ctx');
        Assert::same($loaded->document, ['@context' => ['a' => 'http://x/a']]);
        Assert::same($loaded->contextUrl, null);
        Assert::same($client->requestedUrl, 'https://example.com/ctx');
        Assert::same($client->sent, 1);
        Assert::same($client->lastRequest instanceof StubMessage ? $client->lastRequest->headers : null, [
            'Accept' => 'application/ld+json, application/json;q=0.9, */*;q=0.1',
        ]);
    }

    #[DataProvider('statusProvider')]
    public function statusIsAcceptedOrRefused(int $status, bool $accepted): void
    {
        $client = new StubHttpClient(new StubMessage($status, new StringStream('{}')));
        $loader = new PsrContextLoader($client, $client);

        if ($accepted) {
            Assert::same($loader->load('https://example.com/ctx')->document, []);

            return;
        }

        $failure = $this->failureOf($loader, 'https://example.com/ctx');

        Assert::same($failure->errorCode(), ErrorCode::LoadingDocumentFailed);
        Assert::same($failure->getMessage(), sprintf('Loading "https://example.com/ctx" failed with HTTP status %d', $status));
    }

    public static function statusProvider(): iterable
    {
        yield 'ok' => [200, true];
        yield 'redirect left to the client' => [399, true];
        yield 'bad request' => [400, false];
        yield 'not found' => [404, false];
        yield 'server error' => [503, false];
    }

    public function clientExceptionIsWrapped(): void
    {
        $client = new StubHttpClient(failure: 'connection refused');
        $failure = $this->failureOf(new PsrContextLoader($client, $client), 'https://example.com/ctx');

        Assert::same($failure->errorCode(), ErrorCode::LoadingDocumentFailed);
        Assert::same($failure->getMessage(), 'connection refused');
        Assert::true($failure->getPrevious() instanceof \Throwable);
    }

    #[DataProvider('sizeProvider')]
    public function bodySizeLimitIsInclusive(string $body, bool $sizeKnown, int $maxBytes, bool $accepted): void
    {
        $client = new StubHttpClient(new StubMessage(200, new StringStream($body, $sizeKnown)));
        $loader = new PsrContextLoader($client, $client, maxBytes: $maxBytes);

        if ($accepted) {
            Assert::same($loader->load('https://example.com/ctx')->document, ['k' => 'v']);

            return;
        }

        $failure = $this->failureOf($loader, 'https://example.com/ctx');

        Assert::same($failure->errorCode(), ErrorCode::LoadingDocumentFailed);
        Assert::same($failure->getMessage(), sprintf('Response of "https://example.com/ctx" exceeds %d bytes', $maxBytes));
    }

    public function oversizedDeclaredSizeIsRefusedWithoutReadingTheBody(): void
    {
        $stream = new StringStream('{"k":"v"}');
        $client = new StubHttpClient(new StubMessage(200, $stream));
        $failure = $this->failureOf(new PsrContextLoader($client, $client, maxBytes: 3), 'https://example.com/ctx');

        Assert::same($failure->getMessage(), 'Response of "https://example.com/ctx" exceeds 3 bytes');
        Assert::same($stream->reads, 0);
    }

    public function readsOneByteMoreThanTheLimitAndNoMore(): void
    {
        $stream = new StringStream('{"k":"v"}', sizeKnown: false);
        $client = new StubHttpClient(new StubMessage(200, $stream));
        $this->failureOf(new PsrContextLoader($client, $client, maxBytes: 3), 'https://example.com/ctx');

        Assert::same($stream->lastReadLength, 4);
        Assert::same($stream->reads, 1);
    }

    public static function sizeProvider(): iterable
    {
        $body = '{"k":"v"}';
        $length = strlen($body);

        yield 'declared size at the limit' => [$body, true, $length, true];
        yield 'declared size over the limit' => [$body, true, $length - 1, false];
        yield 'unknown size at the limit' => [$body, false, $length, true];
        yield 'unknown size over the limit' => [$body, false, $length - 1, false];
    }

    public function defaultLimitIsOneMebibyte(): void
    {
        $document = '{"k":"' . str_repeat('a', 1_048_576 - 8) . '"}';
        $client = new StubHttpClient(new StubMessage(200, new StringStream($document)));

        Assert::same(strlen($document), 1_048_576);
        Assert::same(count((new PsrContextLoader($client, $client))->load('https://example.com/ctx')->document), 1);

        $tooLarge = new StubHttpClient(new StubMessage(200, new StringStream($document . ' ')));

        Assert::same($this->failureOf(new PsrContextLoader($tooLarge, $tooLarge), 'https://example.com/ctx')->getMessage(), 'Response of "https://example.com/ctx" exceeds 1048576 bytes');
    }

    #[DataProvider('invalidBodyProvider')]
    public function nonJsonBodyIsRefused(string $body): void
    {
        $client = new StubHttpClient(new StubMessage(200, new StringStream($body)));
        $failure = $this->failureOf(new PsrContextLoader($client, $client), 'https://example.com/ctx');

        Assert::same($failure->errorCode(), ErrorCode::LoadingDocumentFailed);
        Assert::same($failure->getMessage(), 'Response of "https://example.com/ctx" is not a JSON document');
        Assert::true($failure->getPrevious() instanceof \Throwable);
    }

    public static function invalidBodyProvider(): iterable
    {
        yield 'html' => ['<html></html>'];
        yield 'scalar' => ['42'];
        yield 'empty' => [''];
        yield 'too deep' => [str_repeat('[', 600) . str_repeat(']', 600)];
    }

    private function failureOf(PsrContextLoader $loader, string $url): DocumentLoadingException
    {
        try {
            $loader->load($url);
        } catch (DocumentLoadingException $exception) {
            return $exception;
        }

        throw new \LogicException('the loader was expected to refuse the reference');
    }
}
