<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Rasuvaeff\JsonLd\JsonLd;

$expanded = JsonLd::expand([
    '@context' => [
        '@vocab' => 'https://schema.org/',
        'ex' => 'https://example.com/ns#',
        'id' => '@id',
        'type' => '@type',
        'tags' => ['@id' => 'ex:tags', '@container' => '@set'],
        'title' => ['@id' => 'name', '@container' => '@language'],
        'steps' => ['@id' => 'ex:steps', '@container' => '@list'],
    ],
    'id' => 'https://example.com/recipe',
    'type' => 'Recipe',
    'tags' => 'quick',
    'title' => ['en' => 'Soup', 'ru' => 'Суп'],
    'steps' => ['boil', 'serve'],
]);

echo json_encode($expanded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
