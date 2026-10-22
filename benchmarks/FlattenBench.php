<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Benchmarks;

use Rasuvaeff\JsonLd\JsonLd;
use Testo\Bench;

final class FlattenBench
{
    #[Bench(callables: ['floor' => [Documents::class, 'floor']], calls: 2_000, iterations: 10, tolerance: Documents::REPORT_ONLY)]
    public static function smallDocument(): int
    {
        return count(JsonLd::flatten(Documents::small()));
    }

    #[Bench(callables: ['floor' => [Documents::class, 'floor']], calls: 20, iterations: 10, tolerance: Documents::REPORT_ONLY)]
    public static function largeDocument(): int
    {
        return count(JsonLd::flatten(Documents::large()));
    }
}
