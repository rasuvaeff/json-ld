<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\Rdf\BlankNode;
use Rasuvaeff\JsonLd\Rdf\Dataset;
use Rasuvaeff\JsonLd\Rdf\IdentifierIssuer;
use Rasuvaeff\JsonLd\Rdf\Iri;
use Rasuvaeff\JsonLd\Rdf\JsonCanonicalizer;
use Rasuvaeff\JsonLd\Rdf\Literal;
use Rasuvaeff\JsonLd\Rdf\NQuads;
use Rasuvaeff\JsonLd\Rdf\NQuadsWriter;
use Rasuvaeff\JsonLd\Rdf\Quad;
use Rasuvaeff\JsonLd\Rdf\Vocabulary;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(Dataset::class)]
#[Covers(JsonLd::class)]
#[Covers(Quad::class)]
#[Covers(Literal::class)]
#[Covers(BlankNode::class)]
#[Covers(Vocabulary::class)]
#[Covers(NQuads::class)]
#[Covers(NQuadsWriter::class)]
#[Covers(JsonCanonicalizer::class)]
#[Covers(IdentifierIssuer::class)]
#[Covers(Iri::class)]
final class DatasetTest
{
    public function equalsIgnoresBlankNodeLabelsAndOrder(): void
    {
        $left = JsonLd::parseNQuads("_:a <http://p> _:b .\n_:b <http://q> \"x\" .\n");
        $right = JsonLd::parseNQuads("_:y <http://q> \"x\" .\n_:x <http://p> _:y .\n");

        Assert::true($left->equals($right));
        Assert::same($left->canonicalHash(), $right->canonicalHash());
    }

    public function distinguishesDifferentStructure(): void
    {
        $chain = JsonLd::parseNQuads("_:a <http://p> _:b .\n_:b <http://p> _:c .\n");
        $star = JsonLd::parseNQuads("_:a <http://p> _:b .\n_:a <http://p> _:c .\n");

        Assert::false($chain->equals($star));
    }

    public function resolvesSymmetricBlankNodes(): void
    {
        $left = JsonLd::parseNQuads("_:a <http://p> _:b .\n_:b <http://p> _:a .\n");
        $right = JsonLd::parseNQuads("_:m <http://p> _:n .\n_:n <http://p> _:m .\n");

        Assert::true($left->equals($right));
    }
}
