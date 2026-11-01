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
#[Covers(Expander::class)]
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
#[Covers(MapContextLoader::class)]
#[Covers(LoadedContext::class)]
#[Covers(DocumentExpansion::class)]
final class InlineInputTest
{
    public function expandsAnInlineJsonString(): void
    {
        $expanded = JsonLd::expand('{"@context": {"n": "http://s/name"}, "n": "x"}');

        Assert::same($expanded, [['http://s/name' => [['@value' => 'x']]]]);
    }

    public function defaultLoaderRefusesRemoteContexts(): void
    {
        $message = null;

        try {
            JsonLd::expand(['@context' => 'https://example.com/ctx', 'http://s/p' => 'v']);
        } catch (DocumentLoadingException $exception) {
            $message = $exception->errorCode();
        }

        Assert::same($message, ErrorCode::LoadingRemoteContextFailed);
    }

    public function deepNestingIsRejected(): void
    {
        $document = ['http://s/p' => 'leaf'];

        for ($level = 0; $level < 600; $level++) {
            $document = ['http://s/p' => $document];
        }

        $message = null;

        try {
            JsonLd::expand($document);
        } catch (\Rasuvaeff\JsonLd\Exception\InvalidInputException $exception) {
            $message = $exception->getMessage();
        }

        Assert::same($message, 'document nested too deeply');
    }
}
