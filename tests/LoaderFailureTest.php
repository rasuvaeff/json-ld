<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Context\ActiveContext;
use Rasuvaeff\JsonLd\Context\ContextProcessor;
use Rasuvaeff\JsonLd\Context\DefinitionScope;
use Rasuvaeff\JsonLd\Context\Syntax;
use Rasuvaeff\JsonLd\Context\TermDefinition;
use Rasuvaeff\JsonLd\DocumentExpansion;
use Rasuvaeff\JsonLd\Exception\DocumentLoadingException;
use Rasuvaeff\JsonLd\Exception\ErrorCode;
use Rasuvaeff\JsonLd\Exception\InvalidContextException;
use Rasuvaeff\JsonLd\Expander;
use Rasuvaeff\JsonLd\ExpansionRun;
use Rasuvaeff\JsonLd\Iri;
use Rasuvaeff\JsonLd\Json;
use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\Keywords;
use Rasuvaeff\JsonLd\Loader\InlineContextLoader;
use Rasuvaeff\JsonLd\Loader\LoadedContext;
use Rasuvaeff\JsonLd\Loader\MapContextLoader;
use Rasuvaeff\JsonLd\ProcessingOptions;
use Rasuvaeff\JsonLd\Tests\Support\ThrowingContextLoader;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

/**
 * Whatever a loader throws reaches the caller as a DocumentLoadingException:
 * `loading remote context failed` for a context, `loading document failed`
 * for the document itself, the loader's exception kept as the previous one.
 */
#[Test]
#[Covers(DocumentExpansion::class)]
#[Covers(ContextProcessor::class, 'load')]
#[Covers(ExpansionRun::class)]
#[Covers(JsonLd::class)]
#[Covers(Json::class)]
#[Covers(Keywords::class)]
#[Covers(Iri::class)]
#[Covers(ProcessingOptions::class)]
#[Covers(ActiveContext::class)]
#[Covers(Syntax::class)]
#[Covers(TermDefinition::class)]
#[Covers(DefinitionScope::class)]
#[Covers(InlineContextLoader::class)]
#[Covers(MapContextLoader::class)]
#[Covers(LoadedContext::class)]
#[Covers(Expander::class)]
final class LoaderFailureTest
{
    #[DataProvider('failureProvider')]
    public function contextLoaderFailuresBecomeLoadingRemoteContextFailed(\Throwable $failure): void
    {
        $caught = $this->catch(static fn(): array => JsonLd::expand(
            ['@context' => 'https://example.com/ctx', 'http://s/p' => 'v'],
            new ProcessingOptions(loader: new ThrowingContextLoader($failure)),
        ));

        Assert::instanceOf($caught, DocumentLoadingException::class);
        Assert::same($caught?->errorCode(), ErrorCode::LoadingRemoteContextFailed);
        Assert::same($caught?->getPrevious(), $failure);
        Assert::same(
            $caught?->getMessage(),
            'loading remote context failed: "https://example.com/ctx": ' . $failure->getMessage(),
        );
    }

    #[DataProvider('failureProvider')]
    public function documentLoaderFailuresBecomeLoadingDocumentFailed(\Throwable $failure): void
    {
        $caught = $this->catch(static fn(): array => JsonLd::flatten(
            'https://example.com/doc',
            new ProcessingOptions(loader: new ThrowingContextLoader($failure)),
        ));

        Assert::instanceOf($caught, DocumentLoadingException::class);
        Assert::same($caught?->errorCode(), ErrorCode::LoadingDocumentFailed);
        Assert::same($caught?->getPrevious(), $failure);
        Assert::same($caught?->getMessage(), 'loading document failed: "https://example.com/doc": ' . $failure->getMessage());
    }

    public function importedContextFailuresAreWrappedToo(): void
    {
        $failure = new \RuntimeException('offline');

        $caught = $this->catch(static fn(): array => JsonLd::expand(
            ['@context' => ['@version' => 1.1, '@import' => 'https://example.com/import'], 'http://s/p' => 'v'],
            new ProcessingOptions(loader: new ThrowingContextLoader($failure)),
        ));

        Assert::same($caught?->errorCode(), ErrorCode::LoadingRemoteContextFailed);
        Assert::same($caught?->getPrevious(), $failure);
    }

    public function aFailingScopedContextIsAnInvalidScopedContext(): void
    {
        // Create Term Definition validates a scoped context and reports any error as
        // invalid scoped context; the loading failure stays reachable as the previous one
        $caught = $this->catch(static fn(): array => JsonLd::expand(
            ['@context' => ['t' => ['@id' => 'http://s/t', '@context' => 'https://unknown/']], 't' => ['http://s/p' => 'x']],
            new ProcessingOptions(loader: new MapContextLoader([])),
        ));
        $previous = $caught?->getPrevious();

        Assert::instanceOf($caught, InvalidContextException::class);
        Assert::same($caught?->errorCode(), ErrorCode::InvalidScopedContext);
        Assert::instanceOf($previous, DocumentLoadingException::class);
        Assert::same($previous instanceof DocumentLoadingException ? $previous->errorCode() : null, ErrorCode::LoadingRemoteContextFailed);
    }

    public function aContextDocumentWithoutContextIsAnInvalidRemoteContext(): void
    {
        $caught = $this->catch(static fn(): array => JsonLd::expand(
            ['@context' => 'https://example.com/ctx', 'http://s/p' => 'v'],
            new ProcessingOptions(loader: new MapContextLoader(['https://example.com/ctx' => ['name' => 'x']])),
        ));

        Assert::instanceOf($caught, InvalidContextException::class);
        Assert::same($caught?->errorCode(), ErrorCode::InvalidRemoteContext);
    }

    public static function failureProvider(): iterable
    {
        yield 'runtime exception' => [new \RuntimeException('connection refused')];
        yield 'type error' => [new \TypeError('wrong type')];
        yield 'loader exception' => [new DocumentLoadingException(ErrorCode::LoadingDocumentFailed, 'not found')];
    }

    /**
     * @param \Closure(): mixed $operation
     */
    private function catch(\Closure $operation): ?\Rasuvaeff\JsonLd\Exception\JsonLdException
    {
        try {
            $operation();
        } catch (\Rasuvaeff\JsonLd\Exception\JsonLdException $exception) {
            return $exception;
        }

        return null;
    }
}
