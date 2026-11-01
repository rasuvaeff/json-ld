<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Context\Syntax;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(Syntax::class)]
final class SyntaxTest
{
    #[DataProvider('absoluteIriProvider')]
    public function recognizesAbsoluteIris(string $value, bool $expected): void
    {
        Assert::same(Syntax::isAbsoluteIri($value), $expected);
    }

    public static function absoluteIriProvider(): iterable
    {
        yield 'http' => ['http://example.com/a', true];
        yield 'urn' => ['urn:isbn:123', true];
        yield 'unicode' => ['http://example.com/é', true];
        yield 'scheme only' => ['a:', true];
        yield 'no colon' => ['example.com', false];
        yield 'empty' => ['', false];
        yield 'colon first' => [':a', false];
        yield 'scheme starting with a digit' => ['1a:b', false];
        yield 'scheme with a space' => ['a b:c', false];
        yield 'space in the path' => ['http://example.com/a b', false];
        yield 'angle bracket' => ['http://example.com/<a>', false];
        yield 'caret' => ['http://example.com/a^b', false];
        yield 'two fragments' => ['http://example.com/#a#b', false];
        yield 'one fragment' => ['http://example.com/#a', true];
        yield 'bad percent escape' => ['http://example.com/%zz', false];
        yield 'truncated percent escape' => ['http://example.com/%a', false];
        yield 'good percent escape' => ['http://example.com/%aF', true];
        yield 'invalid utf-8' => ["http://example.com/\xFF", false];
        yield 'newline at the end' => ["http://example.com/\n", false];
    }

    #[DataProvider('keywordLikeProvider')]
    public function recognizesKeywordLikeForms(string $value, bool $expected): void
    {
        Assert::same(Syntax::looksLikeKeyword($value), $expected);
    }

    public static function keywordLikeProvider(): iterable
    {
        yield 'at and letters' => ['@foo', true];
        yield 'single letter' => ['@a', true];
        yield 'bare at' => ['@', false];
        yield 'with a digit' => ['@a1', false];
        yield 'with a dash' => ['@a-b', false];
        yield 'prefixed' => ['x@a', false];
        yield 'trailing newline' => ["@a\n", false];
    }

    #[DataProvider('genDelimProvider')]
    public function recognizesGenDelims(string $value, bool $expected): void
    {
        Assert::same(Syntax::endsWithGenDelim($value), $expected);
    }

    public static function genDelimProvider(): iterable
    {
        yield 'empty' => ['', false];
        yield 'letter' => ['ab', false];
        yield 'colon' => ['a:', true];
        yield 'slash' => ['a/', true];
        yield 'question mark' => ['a?', true];
        yield 'hash' => ['a#', true];
        yield 'open bracket' => ['a[', true];
        yield 'close bracket' => ['a]', true];
        yield 'at' => ['a@', true];
        yield 'delimiter only inside' => ['a:b', false];
    }

    public function recognizesBlankNodes(): void
    {
        Assert::true(Syntax::isBlankNode('_:b0'));
        Assert::false(Syntax::isBlankNode('x_:b0'));
        Assert::true(Syntax::isBlankNodeId('_:b0'));
        Assert::false(Syntax::isBlankNodeId('b0'));
    }

    public function checksSchemes(): void
    {
        Assert::true(Syntax::hasValidScheme('a'));
        Assert::true(Syntax::hasValidScheme('a+b-c.d1'));
        Assert::false(Syntax::hasValidScheme(''));
        Assert::false(Syntax::hasValidScheme('1a'));
        Assert::false(Syntax::hasValidScheme('a_b'));
        Assert::false(Syntax::hasValidScheme("a\n"));
    }
}
