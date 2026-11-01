<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\ProcessingOptions;

$loader = require __DIR__ . '/support.php';
$options = new ProcessingOptions(loader: $loader);
$article = require __DIR__ . '/article.php';

echo 'Flattened nodes: ', count(JsonLd::flatten($article, $options)), "\n";

$document = JsonLd::document($article, $options);

foreach ($document->defaultGraph()->byType('https://schema.org/Article') as $node) {
    echo $node->value('https://schema.org/headline'), "\n";

    // the author is embedded without @id: it is still a node, labelled _:b0
    $author = $node->reference('https://schema.org/author');

    echo '  author: ', $author?->value('https://schema.org/name') ?? 'none', ' (', $author?->id() ?? '-', ")\n";

    $video = $node->reference('https://schema.org/video');

    echo '  video: ', $video?->value('https://schema.org/name') ?? 'none', ' (', $video?->value('https://schema.org/duration') ?? '-', ")\n";
}

// Language-tagged values keep their tag.
$city = JsonLd::document([
    '@context' => ['@vocab' => 'https://schema.org/'],
    '@id' => 'https://example.com/moscow',
    'name' => [['@value' => 'Moscow', '@language' => 'en'], ['@value' => 'Москва', '@language' => 'ru']],
])->defaultGraph()->node('https://example.com/moscow');

foreach ($city?->valueObjects('https://schema.org/name') ?? [] as $name) {
    echo '  name@', $name->language ?? '-', ': ', $name->value, "\n";
}
