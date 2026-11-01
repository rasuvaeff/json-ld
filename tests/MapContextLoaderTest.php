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
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(MapContextLoader::class)]
#[Covers(ExpansionRun::class)]
#[Covers(JsonLd::class)]
#[Covers(Json::class)]
#[Covers(Keywords::class)]
#[Covers(Iri::class)]
#[Covers(ProcessingOptions::class)]
#[Covers(ContextProcessor::class)]
#[Covers(ActiveContext::class)]
#[Covers(Syntax::class)]
#[Covers(TermDefinition::class)]
#[Covers(DefinitionScope::class)]
#[Covers(InlineContextLoader::class)]
#[Covers(LoadedContext::class)]
#[Covers(DocumentExpansion::class)]
#[Covers(Expander::class)]
final class MapContextLoaderTest
{
    public function servesKnownDocuments(): void
    {
        $document = ['@context' => ['@vocab' => 'https://schema.org/']];
        $loaded = (new MapContextLoader(['https://schema.org/' => $document]))->load('https://schema.org/');

        Assert::same($loaded->documentUrl, 'https://schema.org/');
        Assert::same($loaded->document, $document);
        Assert::null($loaded->contextUrl);
    }

    public function refusesUnknownUrlsExactly(): void
    {
        $loader = new MapContextLoader(['https://schema.org/' => ['@context' => []]]);
        $caught = null;

        try {
            $loader->load('https://schema.org');
        } catch (DocumentLoadingException $exception) {
            $caught = $exception;
        }

        Assert::same($caught?->errorCode(), ErrorCode::LoadingDocumentFailed);
        Assert::same($caught?->getMessage(), '"https://schema.org" is not in the map of known documents');
    }

    public function shortensLongReferencesInTheMessage(): void
    {
        $caught = null;

        try {
            (new MapContextLoader([]))->load('https://example.com/' . str_repeat('a', 100));
        } catch (DocumentLoadingException $exception) {
            $caught = $exception->getMessage();
        }

        Assert::same($caught, '"https://example.com/' . str_repeat('a', 39) . '…" is not in the map of known documents');
    }

    public function expandsWithAMappedContext(): void
    {
        $options = new ProcessingOptions(loader: new MapContextLoader([
            'https://schema.org/' => ['@context' => ['@vocab' => 'https://schema.org/']],
        ]));

        Assert::same(
            JsonLd::expand(['@context' => 'https://schema.org/', 'name' => 'Alice'], $options),
            [['https://schema.org/name' => [['@value' => 'Alice']]]],
        );
    }
}
