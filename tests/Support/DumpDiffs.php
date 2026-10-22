<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Rasuvaeff\JsonLd\ExpandOptions;
use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\Tests\Support\FixtureContextLoader;
use Rasuvaeff\JsonLd\Tests\Support\W3cManifest;
use Rasuvaeff\JsonLd\Tests\W3cToRdfTest;

$loader = new FixtureContextLoader();
$suite = $argv[1] ?? 'toRdf';
$filter = $argv[2] ?? null;

foreach ((new W3cManifest())->cases($suite) as $name => $case) {
    if ($filter !== null && !str_contains($name, $filter)) {
        continue;
    }

    $opts = new ExpandOptions(base: $case['base'], expandContext: $case['expandContext'], loader: $loader);

    try {
        $got = JsonLd::serializeNQuads(JsonLd::toRdf((array) $case['input'], $opts));
    } catch (Throwable $e) {
        if ($e->getMessage() === $case['expectedError']) {
            continue;
        }

        echo "== {$name}\n   THROWN: {$e->getMessage()} (want: " . ($case['expectedError'] ?? 'success') . ")\n";

        continue;
    }

    if ($case['expectedError'] !== null) {
        echo "== {$name}\n   NO ERROR (want: {$case['expectedError']})\n";

        continue;
    }

    $want = (string) $case['expected'];

    $g = W3cToRdfTest::normalized($got);
    $w = W3cToRdfTest::normalized($want);

    if ($g === $w) {
        continue;
    }

    echo "== {$name}\n";

    foreach (array_diff($w, $g) as $line) {
        echo "   - {$line}\n";
    }

    foreach (array_diff($g, $w) as $line) {
        echo "   + {$line}\n";
    }
}
