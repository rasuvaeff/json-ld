<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Exception\DocumentLoadingException;
use Rasuvaeff\JsonLd\Exception\ErrorCode;
use Rasuvaeff\JsonLd\Json;
use Rasuvaeff\JsonLd\Loader\InlineContextLoader;
use Rasuvaeff\JsonLd\Loader\LoadedContext;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(InlineContextLoader::class)]
#[Covers(LoadedContext::class)]
#[Covers(Json::class)]
final class InlineContextLoaderTest
{
    #[DataProvider('inlineProvider')]
    public function loadsInlineJson(string $reference, array $expected): void
    {
        $loaded = (new InlineContextLoader())->load($reference);

        Assert::same($loaded->documentUrl, '');
        Assert::same($loaded->document, $expected);
        Assert::same($loaded->contextUrl, null);
    }

    public static function inlineProvider(): iterable
    {
        yield 'object' => ['{"a": 1}', ['a' => 1]];
        yield 'array' => ['[1, 2]', [1, 2]];
        yield 'object after whitespace' => ["  \n\t{\"a\": 1}", ['a' => 1]];
        yield 'array after whitespace' => ["\r\n [true]", [true]];
    }

    #[DataProvider('notInlineProvider')]
    public function refusesAnythingThatIsNotInlineJson(string $reference, string $shown): void
    {
        $failure = $this->failureOf($reference);

        Assert::same($failure->errorCode(), ErrorCode::LoadingDocumentFailed);
        Assert::same($failure->getMessage(), sprintf('"%s" is not inline JSON; loading remote documents requires an explicitly configured loader', $shown));
        Assert::same($failure->getPrevious(), null);
    }

    public static function notInlineProvider(): iterable
    {
        yield 'empty' => ['', ''];
        yield 'blank' => ["  \n", "  \n"];
        yield 'url' => ['https://example.com/ctx.jsonld', 'https://example.com/ctx.jsonld'];
        yield 'scalar' => ['"text"', '"text"'];
        yield 'object brace after other text' => ['x{', 'x{'];
        yield 'short reference at the width limit' => [str_repeat('a', 60), str_repeat('a', 60)];
        yield 'long reference is cut at sixty columns' => [str_repeat('a', 61), str_repeat('a', 59) . '…'];
        yield 'wide characters are cut by columns' => [str_repeat('字', 40), str_repeat('字', 29) . '…'];
    }

    public function wrapsMalformedJson(): void
    {
        $failure = $this->failureOf('{"a": ');

        Assert::same($failure->errorCode(), ErrorCode::LoadingDocumentFailed);
        Assert::true($failure->getPrevious() instanceof \Throwable);
    }

    public function wrapsDocumentsThatAreTooDeep(): void
    {
        $failure = $this->failureOf(str_repeat('[', 600) . str_repeat(']', 600));

        Assert::same($failure->errorCode(), ErrorCode::LoadingDocumentFailed);
        Assert::same($failure->getMessage(), ErrorCode::DocumentTooDeep->value);
        Assert::true($failure->getPrevious() instanceof \Throwable);
    }

    private function failureOf(string $reference): DocumentLoadingException
    {
        try {
            (new InlineContextLoader())->load($reference);
        } catch (DocumentLoadingException $exception) {
            return $exception;
        }

        throw new \LogicException('the loader was expected to refuse the reference');
    }
}
