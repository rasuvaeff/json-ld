<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Benchmarks;

use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\Rdf\Dataset;
use Testo\Bench;

final class CanonicalizeBench
{
    #[Bench(callables: ['floor' => [Documents::class, 'floor']], calls: 2_000, iterations: 10, tolerance: Documents::REPORT_ONLY)]
    public static function treeShaped(): int
    {
        static $dataset = null;
        $dataset ??= JsonLd::toRdf(Documents::small());

        return strlen($dataset->canonicalNQuads());
    }

    #[Bench(callables: ['floor' => [Documents::class, 'floor']], calls: 200, iterations: 10, tolerance: Documents::REPORT_ONLY)]
    public static function symmetricCycle(): int
    {
        static $dataset = null;
        $dataset ??= self::cycle(20);

        return strlen($dataset->canonicalNQuads());
    }

    private static function cycle(int $size): Dataset
    {
        $nquads = '';

        for ($index = 0; $index < $size; $index++) {
            $nquads .= sprintf("_:n%d <http://p> _:n%d .\n", $index, ($index + 1) % $size);
        }

        return JsonLd::parseNQuads($nquads);
    }
}
