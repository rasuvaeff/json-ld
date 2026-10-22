<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\Rdf\Dataset;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(Dataset::class)]
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
