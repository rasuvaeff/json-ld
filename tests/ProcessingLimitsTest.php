<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Context\ActiveContext;
use Rasuvaeff\JsonLd\Context\ContextProcessor;
use Rasuvaeff\JsonLd\Context\DefinitionScope;
use Rasuvaeff\JsonLd\Context\Syntax;
use Rasuvaeff\JsonLd\Context\TermDefinition;
use Rasuvaeff\JsonLd\DocumentExpansion;
use Rasuvaeff\JsonLd\Exception\ErrorCode;
use Rasuvaeff\JsonLd\Exception\InvalidInputException;
use Rasuvaeff\JsonLd\Exception\JsonLdException;
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
use Testo\Data\DataProvider;
use Testo\Test;

/**
 * The guards for untrusted input: the size of an inline JSON string and the
 * nesting depth, both when a string is decoded and during expansion.
 */
#[Test]
#[Covers(ProcessingOptions::class)]
#[Covers(Json::class)]
#[Covers(ExpansionRun::class, 'expand')]
#[Covers(JsonLd::class)]
#[Covers(Keywords::class)]
#[Covers(Iri::class)]
#[Covers(ContextProcessor::class)]
#[Covers(ActiveContext::class)]
#[Covers(Syntax::class)]
#[Covers(TermDefinition::class)]
#[Covers(DefinitionScope::class)]
#[Covers(InlineContextLoader::class)]
#[Covers(MapContextLoader::class)]
#[Covers(LoadedContext::class)]
#[Covers(DocumentExpansion::class)]
#[Covers(Expander::class)]
final class ProcessingLimitsTest
{
    public function aStringAtTheSizeLimitIsAccepted(): void
    {
        $json = '{"http://s/p": "x", "http://s/q": "y"}';

        Assert::same(
            JsonLd::expand($json, new ProcessingOptions(maxBytes: strlen($json))),
            [['http://s/p' => [['@value' => 'x']], 'http://s/q' => [['@value' => 'y']]]],
        );
    }

    public function aStringOverTheSizeLimitIsRefused(): void
    {
        $json = '{"http://s/p": "x"}';
        $caught = $this->catch(static fn(): array => JsonLd::expand($json, new ProcessingOptions(maxBytes: strlen($json) - 1)));

        Assert::instanceOf($caught, InvalidInputException::class);
        Assert::same($caught?->errorCode(), ErrorCode::DocumentTooLarge);
        Assert::same($caught?->getMessage(), 'JSON document of 19 bytes exceeds the limit of 18 bytes');
    }

    public function decodedArraysIgnoreTheSizeLimit(): void
    {
        Assert::same(
            JsonLd::expand(['http://s/p' => 'a long value'], new ProcessingOptions(maxBytes: 1)),
            [['http://s/p' => [['@value' => 'a long value']]]],
        );
    }

    #[DataProvider('depthProvider')]
    public function depthIsLimitedForStringsAndArrays(string|array $input, int $maxDepth, bool $accepted): void
    {
        $caught = $this->catch(static fn(): array => JsonLd::expand($input, new ProcessingOptions(maxDepth: $maxDepth)));

        Assert::same($caught?->errorCode(), $accepted ? null : ErrorCode::DocumentTooDeep);
    }

    public static function depthProvider(): iterable
    {
        // three levels: object, array, object
        $json = '{"http://s/p": [{"http://s/q": "x"}]}';
        $array = ['http://s/p' => [['http://s/q' => 'x']]];

        yield 'string at the limit' => [$json, 3, true];
        yield 'string over the limit' => [$json, 2, false];
        yield 'array at the limit' => [$array, 3, true];
        yield 'array over the limit' => [$array, 2, false];
        // depth is nesting, not the number of arrays: siblings do not add up
        yield 'wide array at the limit' => [['http://s/p' => [['http://s/q' => 'x']], 'http://s/r' => [['http://s/q' => 'y']], 'http://s/s' => [['http://s/q' => 'z']]], 3, true];
    }

    public function theDefaultDepthStopsDeeplyNestedStrings(): void
    {
        $json = str_repeat('[', 600) . str_repeat(']', 600);

        Assert::same($this->catch(static fn(): array => JsonLd::expand($json))?->errorCode(), ErrorCode::DocumentTooDeep);
        Assert::same(ProcessingOptions::DEFAULT_MAX_DEPTH, 512);
    }

    public function malformedJsonIsALoadingFailure(): void
    {
        $caught = $this->catch(static fn(): array => JsonLd::expand('{"a": '));

        Assert::same($caught?->errorCode(), ErrorCode::LoadingDocumentFailed);
        // PHP 8.6 adds the location ("Syntax error near location 1:7")
        Assert::true(str_starts_with($caught?->getMessage(), 'Syntax error'));
        Assert::instanceOf($caught?->getPrevious(), \JsonException::class);
    }

    #[DataProvider('invalidLimitProvider')]
    public function limitsMustBePositive(int $maxDepth, ?int $maxBytes, string $message): void
    {
        $caught = null;

        try {
            new ProcessingOptions(maxDepth: $maxDepth, maxBytes: $maxBytes);
        } catch (\InvalidArgumentException $exception) {
            $caught = $exception->getMessage();
        }

        Assert::same($caught, $message);
    }

    public static function invalidLimitProvider(): iterable
    {
        yield 'zero depth' => [0, null, 'Maximum depth must be between 1 and 2147483646, 0 given'];
        yield 'depth json_decode cannot take' => [2_147_483_647, null, 'Maximum depth must be between 1 and 2147483646, 2147483647 given'];
        yield 'zero size' => [1, 0, 'Maximum size must be positive, 0 given'];
        yield 'negative size' => [1, -5, 'Maximum size must be positive, -5 given'];
    }

    public function theSmallestLimitsAreAccepted(): void
    {
        $options = new ProcessingOptions(maxDepth: 1, maxBytes: 1);
        $deepest = new ProcessingOptions(maxDepth: ProcessingOptions::MAX_DEPTH_LIMIT);

        Assert::same([$options->maxDepth, $options->maxBytes, $deepest->maxDepth], [1, 1, 2_147_483_646]);
        Assert::same(JsonLd::expand('[[1]]', $deepest), []);
    }

    /**
     * @param \Closure(): mixed $operation
     */
    private function catch(\Closure $operation): ?JsonLdException
    {
        try {
            $operation();
        } catch (JsonLdException $exception) {
            return $exception;
        }

        return null;
    }
}
