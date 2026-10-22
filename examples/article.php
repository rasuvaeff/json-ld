<?php

declare(strict_types=1);

// Shared sample document: a schema.org page with an Article and a VideoObject.
return [
    '@context' => 'https://schema.org/',
    '@graph' => [
        [
            '@id' => 'https://example.com/articles/1',
            '@type' => 'Article',
            'headline' => 'JSON-LD in practice',
            'author' => ['@type' => 'Person', 'name' => 'Alice'],
            'video' => ['@id' => 'https://example.com/videos/1'],
        ],
        [
            '@id' => 'https://example.com/videos/1',
            '@type' => 'VideoObject',
            'name' => 'Intro',
            'duration' => 'PT3M',
        ],
    ],
];
