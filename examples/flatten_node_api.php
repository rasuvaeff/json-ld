<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Rasuvaeff\JsonLd\ExpandOptions;
use Rasuvaeff\JsonLd\JsonLd;

$loader = require __DIR__ . '/support.php';
$options = new ExpandOptions(loader: $loader);
$article = require __DIR__ . '/article.php';

echo 'Flattened nodes: ', count(JsonLd::flatten($article, $options)), "\n";

$document = JsonLd::document($article, $options);

foreach ($document->defaultGraph()->byType('https://schema.org/Article') as $node) {
    echo $node->value('https://schema.org/headline'), "\n";

    $video = $node->reference('https://schema.org/video');

    echo '  video: ', $video?->value('https://schema.org/name') ?? 'none', ' (', $video?->value('https://schema.org/duration') ?? '-', ")\n";
}
