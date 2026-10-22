<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Exception\InvalidContextException;
use Rasuvaeff\JsonLd\Expander;
use Rasuvaeff\JsonLd\JsonLd;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(Expander::class)]
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
        } catch (InvalidContextException $exception) {
            $message = $exception->getMessage();
        }

        Assert::same($message, 'loading remote context failed');
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
