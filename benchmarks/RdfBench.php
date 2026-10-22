<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Benchmarks;

use Rasuvaeff\JsonLd\JsonLd;
use Testo\Bench;

final class RdfBench
{
    #[Bench(callables: ['floor' => [Documents::class, 'floor']], calls: 2_000, iterations: 10, tolerance: Documents::REPORT_ONLY)]
    public static function toRdf(): int
    {
        return count(JsonLd::toRdf(Documents::small())->quads());
    }

    #[Bench(callables: ['floor' => [Documents::class, 'floor']], calls: 2_000, iterations: 10, tolerance: Documents::REPORT_ONLY)]
    public static function fromRdf(): int
    {
        static $dataset = null;
        $dataset ??= JsonLd::toRdf(Documents::small());

        return count(JsonLd::fromRdf($dataset));
    }
}
