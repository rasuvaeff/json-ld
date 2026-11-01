<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Exception\InvalidNQuadsException;
use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\Rdf\NQuads;
use Rasuvaeff\JsonLd\Rdf\NQuadsWriter;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(NQuads::class)]
#[Covers(NQuadsWriter::class)]
final class NQuadsTest
{
    public function ignoresCommentsAndBlankLines(): void
    {
        $dataset = JsonLd::parseNQuads("# comment\n\n<http://a> <http://b> \"c\" . # trailing\n");

        Assert::same(count($dataset->quads), 1);
    }

    public function roundTripsEscapes(): void
    {
        $text = "<http://a> <http://b> \"line\\nbreak \\\"quoted\\\" \\\\ tab\\t\"@en-US .\n";

        Assert::same(JsonLd::serializeNQuads(JsonLd::parseNQuads($text)), $text);
    }

    #[DataProvider('invalidProvider')]
    public function rejectsInvalidSyntax(string $line): void
    {
        $thrown = false;

        try {
            JsonLd::parseNQuads($line);
        } catch (InvalidNQuadsException) {
            $thrown = true;
        }

        Assert::true($thrown);
    }

    public static function invalidProvider(): iterable
    {
        yield 'missing dot' => ['<http://a> <http://b> <http://c>'];
        yield 'literal subject' => ['"x" <http://b> <http://c> .'];
        yield 'blank predicate' => ['<http://a> _:b <http://c> .'];
        yield 'two terms' => ['<http://a> <http://b> .'];
        yield 'garbage after dot' => ['<http://a> <http://b> <http://c> . x'];
        yield 'newline in literal' => ["<http://a> <http://b> \"x\n\" ."];
    }
}
