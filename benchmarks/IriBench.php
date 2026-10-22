<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Benchmarks;

use Rasuvaeff\JsonLd\Iri;
use Testo\Bench;

final class IriBench
{
    #[Bench(callables: ['floor' => [Documents::class, 'floor']], calls: 20_000, iterations: 10, tolerance: Documents::REPORT_ONLY)]
    public static function resolve(): string
    {
        return (string) Iri::of('http://a/b/c/d;p?q')->resolve(Iri::of('../../g/./h?y#s'));
    }
}
