<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Benchmarks;

use Rasuvaeff\JsonLd\JsonLd;
use Testo\Bench;

final class NQuadsBench
{
    #[Bench(callables: ['floor' => [Documents::class, 'floor']], calls: 2_000, iterations: 10, tolerance: Documents::REPORT_ONLY)]
    public static function serialize(): int
    {
        static $dataset = null;
        $dataset ??= JsonLd::toRdf(Documents::small());

        return strlen(JsonLd::serializeNQuads($dataset));
    }

    #[Bench(callables: ['floor' => [Documents::class, 'floor']], calls: 2_000, iterations: 10, tolerance: Documents::REPORT_ONLY)]
    public static function parse(): int
    {
        static $text = null;
        $text ??= JsonLd::serializeNQuads(JsonLd::toRdf(Documents::small()));

        return count(JsonLd::parseNQuads($text)->quads());
    }
}
