<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Rdf\JsonCanonicalizer;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(JsonCanonicalizer::class)]
final class JsonCanonicalizerTest
{
    #[DataProvider('scalarProvider')]
    public function serializesScalars(mixed $value, string $expected): void
    {
        Assert::same(JsonCanonicalizer::serialize($value), $expected);
    }

    public static function scalarProvider(): iterable
    {
        yield 'null' => [null, 'null'];
        yield 'true' => [true, 'true'];
        yield 'false' => [false, 'false'];
        yield 'integer' => [42, '42'];
        yield 'negative integer' => [-7, '-7'];
        yield 'object without a JSON form' => [new \stdClass(), 'null'];
        yield 'empty list' => [[], '[]'];
        yield 'empty string' => ['', '""'];
    }

    #[DataProvider('numberProvider')]
    public function serializesNumbersLikeEcmaScript(float $value, string $expected): void
    {
        Assert::same(JsonCanonicalizer::serialize($value), $expected);
    }

    public static function numberProvider(): iterable
    {
        yield 'zero' => [0.0, '0'];
        yield 'negative zero' => [-0.0, '0'];
        yield 'whole number' => [1.0, '1'];
        yield 'negative whole number' => [-1.0, '-1'];
        yield 'fraction' => [4.5, '4.5'];
        yield 'negative fraction' => [-4.5, '-4.5'];
        yield 'fraction with long integer part' => [123456789.125, '123456789.125'];
        yield 'rfc example' => [333333333.33333329, '333333333.3333333'];
        yield 'largest safe integer' => [9007199254740991.0, '9007199254740991'];
        yield 'twenty digit integer' => [1e20, '100000000000000000000'];
        yield 'twenty one digit integer' => [1e21, '1e+21'];
        yield 'negative twenty one digit integer' => [-1e21, '-1e+21'];
        yield 'mantissa with exponent' => [1.5e21, '1.5e+21'];
        yield 'long mantissa with exponent' => [1.2345e25, '1.2345e+25'];
        yield 'twenty one digits with a fraction digit' => [123456789012345680000.0, '123456789012345680000'];
        yield 'huge' => [1.7976931348623157e308, '1.7976931348623157e+308'];
        yield 'one millionth' => [1e-6, '0.000001'];
        yield 'small decimal' => [0.00001234, '0.00001234'];
        yield 'just below a tenth' => [0.05, '0.05'];
        yield 'one ten millionth' => [1e-7, '1e-7'];
        yield 'negative one ten millionth' => [-1e-7, '-1e-7'];
        yield 'small mantissa with exponent' => [1.5e-7, '1.5e-7'];
        yield 'smallest' => [5e-324, '5e-324'];
        yield 'half' => [0.5, '0.5'];
        yield 'negative half' => [-0.5, '-0.5'];
        yield 'negative one millionth' => [-1e-6, '-0.000001'];
        yield 'negative mantissa with exponent' => [-1.5e21, '-1.5e+21'];
        yield 'negative small mantissa with exponent' => [-1.5e-7, '-1.5e-7'];
        yield 'infinity has no JSON form' => [INF, ''];
        yield 'negative infinity has no JSON form' => [-INF, '-'];
    }

    #[DataProvider('stringProvider')]
    public function serializesStrings(string $value, string $expected): void
    {
        Assert::same(JsonCanonicalizer::serialize($value), $expected);
    }

    public static function stringProvider(): iterable
    {
        yield 'plain' => ['abc', '"abc"'];
        yield 'quote and backslash' => ["a\"b\\c", '"a\\"b\\\\c"'];
        yield 'slash stays' => ['a/b', '"a/b"'];
        yield 'control characters' => ["\n\r\t\x08\x0C", '"\\n\\r\\t\\b\\f"'];
        yield 'low control character' => ["\x01\x1F", '"\\u0001\\u001f"'];
        yield 'delete stays raw' => ["\x7F", "\"\x7F\""];
        yield 'unicode stays raw' => ["é€\u{1F600}", "\"é€\u{1F600}\""];
        yield 'line terminators stay raw' => ["\u{2028}\u{2029}", "\"\u{2028}\u{2029}\""];
        yield 'invalid utf-8 is replaced' => ["a\xFFb", "\"a\u{FFFD}b\""];
    }

    public function serializesNestedStructuresWithoutWhitespace(): void
    {
        Assert::same(
            JsonCanonicalizer::serialize(['list' => [1, 'two', [true, null]], 'map' => ['b' => 1.5, 'a' => []]]),
            '{"list":[1,"two",[true,null]],"map":{"a":[],"b":1.5}}',
        );
    }

    public function sortsKeysByUtf16CodeUnits(): void
    {
        Assert::same(
            JsonCanonicalizer::serialize(["\u{FFFF}" => 1, "\u{10000}" => 2, 'z' => 3, "\u{E9}" => 4, 'A' => 5]),
            "{\"A\":5,\"z\":3,\"\u{E9}\":4,\"\u{10000}\":2,\"\u{FFFF}\":1}",
        );
    }

    public function sortsIntegerLikeKeysAsStrings(): void
    {
        Assert::same(JsonCanonicalizer::serialize([10 => 'a', 9 => 'b', 100 => 'c']), '{"10":"a","100":"c","9":"b"}');
    }

    public function escapesKeysLikeStrings(): void
    {
        Assert::same(JsonCanonicalizer::serialize(["a\"b" => 1, "\n" => 2]), '{"\\n":2,"a\\"b":1}');
    }
}
