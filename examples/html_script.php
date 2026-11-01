<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\ProcessingOptions;

// Needs ext-dom (present in the composer:2 image).
$html = <<<'HTML'
    <html><head>
    <script type="application/ld+json">{"@context": "https://schema.org/", "@type": "Article", "headline": "Hello"}</script>
    <script type="application/ld+json">[{"@context": "https://schema.org/", "@type": "VideoObject", "name": "Clip"}]</script>
    <script>var ignored = 1;</script>
    </head></html>
    HTML;

$loader = require __DIR__ . '/support.php';
$options = new ProcessingOptions(loader: $loader, base: 'https://example.com/page');

$page = new DOMDocument();
$page->loadHTML($html, LIBXML_NOERROR);

foreach ((new DOMXPath($page))->query('//script[@type="application/ld+json"]') ?: [] as $script) {
    try {
        $nodes = JsonLd::expand($script->textContent, $options);
    } catch (Throwable $exception) {
        echo 'Skipped a broken block: ', $exception->getMessage(), "\n";

        continue;
    }

    foreach ($nodes as $node) {
        echo json_encode($node, JSON_UNESCAPED_SLASHES), "\n";
    }
}
