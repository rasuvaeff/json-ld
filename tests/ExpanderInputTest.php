<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\DocumentExpansion;
use Rasuvaeff\JsonLd\Exception\DocumentLoadingException;
use Rasuvaeff\JsonLd\Exception\InvalidJsonException;
use Rasuvaeff\JsonLd\Expander;
use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\ProcessingOptions;
use Rasuvaeff\JsonLd\Tests\Support\RecordingContextLoader;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(Expander::class)]
#[Covers(DocumentExpansion::class)]
final class ExpanderInputTest
{
    public function decodesInlineJsonAfterLeadingWhitespace(): void
    {
        $expected = [['http://s/p' => [['@value' => 'x']]]];

        Assert::same(JsonLd::expand('{"http://s/p": "x"}'), $expected);
        Assert::same(JsonLd::expand("  \n\t{\"http://s/p\": \"x\"}"), $expected);
        Assert::same(JsonLd::expand('[{"http://s/p": "x"}]'), $expected);
        Assert::same(JsonLd::expand(" \n[{\"http://s/p\": \"x\"}]"), $expected);
    }

    public function neverAsksTheLoaderForInlineJson(): void
    {
        foreach (['{"http://s/p": "x"}', "  \n{\"http://s/p\": \"x\"}", '[{"http://s/p": "x"}]', "\t[{\"http://s/p\": \"x\"}]"] as $input) {
            $loader = new RecordingContextLoader();
            $expanded = JsonLd::expand($input, new ProcessingOptions(loader: $loader));

            Assert::same($loader->requested, []);
            Assert::same($expanded, [['http://s/p' => [['@value' => 'x']]]]);
        }
    }

    public function reportsMalformedInlineJsonAsInvalidJson(): void
    {
        $thrown = null;

        try {
            JsonLd::expand('  {bad');
        } catch (\Throwable $exception) {
            $thrown = $exception::class;
        }

        Assert::same($thrown, InvalidJsonException::class);
    }

    public function sendsNonJsonStringsToTheLoader(): void
    {
        foreach (['', '   ', 'http://example.com/doc', 'x{'] as $input) {
            $thrown = false;

            try {
                JsonLd::expand($input);
            } catch (DocumentLoadingException) {
                $thrown = true;
            }

            Assert::true($thrown);
        }
    }

    public function resolvesContextReferencesAgainstTheDocumentUrlFirst(): void
    {
        $document = ['@context' => 'ctx.json', 'http://s/p' => 'v'];

        $both = new RecordingContextLoader();
        JsonLd::expand($document, new ProcessingOptions(base: 'http://base/x/', documentUrl: 'http://doc/y/', loader: $both));

        $baseOnly = new RecordingContextLoader();
        JsonLd::expand($document, new ProcessingOptions(base: 'http://base/x/', loader: $baseOnly));

        $documentOnly = new RecordingContextLoader();
        JsonLd::expand($document, new ProcessingOptions(documentUrl: 'http://doc/y/', loader: $documentOnly));

        Assert::same($both->requested, ['http://doc/y/ctx.json']);
        Assert::same($baseOnly->requested, ['http://base/x/ctx.json']);
        Assert::same($documentOnly->requested, ['http://doc/y/ctx.json']);
    }

    public function resolvesTheExpandContextAgainstTheDocumentUrlFirst(): void
    {
        $loader = new RecordingContextLoader();
        JsonLd::expand(
            ['http://s/p' => 'v'],
            new ProcessingOptions(base: 'http://base/x/', documentUrl: 'http://doc/y/', expandContext: 'ctx.json', loader: $loader),
        );

        Assert::same($loader->requested, ['http://doc/y/ctx.json']);
    }

    public function acceptsWrappedAndBareExpandContexts(): void
    {
        $document = ['n' => 'x'];
        $expected = [['http://s/name' => [['@value' => 'x']]]];

        $bare = new ProcessingOptions(expandContext: ['n' => 'http://s/name']);
        $wrapped = new ProcessingOptions(expandContext: ['@context' => ['n' => 'http://s/name']]);
        $listed = new ProcessingOptions(expandContext: [['n' => 'http://s/name']]);
        $inline = new ProcessingOptions(expandContext: '{"@context": {"n": "http://s/name"}}');

        Assert::same(JsonLd::expand($document, $bare), $expected);
        Assert::same(JsonLd::expand($document, $wrapped), $expected);
        Assert::same(JsonLd::expand($document, $listed), $expected);
        Assert::same(JsonLd::expand($document, $inline), $expected);
    }
}
