<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Rasuvaeff\JsonLd\Exception\JsonLdException;
use Rasuvaeff\JsonLd\ExpandOptions;
use Rasuvaeff\JsonLd\JsonLd;

$document = ['@context' => 'https://schema.org/', 'name' => 'Alice'];

// The default loader never touches the network: a remote reference fails.
try {
    JsonLd::expand($document);
} catch (JsonLdException $exception) {
    echo 'Default loader: ', $exception->getMessage(), "\n";
}

// Tests (and offline tools) inject a loader that serves known contexts only.
$loader = require __DIR__ . '/support.php';

echo 'Known context: ', json_encode(JsonLd::expand($document, new ExpandOptions(loader: $loader)), JSON_UNESCAPED_SLASHES), "\n";

try {
    JsonLd::expand(['@context' => 'https://example.com/unknown'], new ExpandOptions(loader: $loader));
} catch (JsonLdException $exception) {
    echo 'Unknown context: ', $exception->getMessage(), "\n";
}
