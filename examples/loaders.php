<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Rasuvaeff\JsonLd\Exception\JsonLdException;
use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\ProcessingOptions;

$document = ['@context' => 'https://schema.org/', 'name' => 'Alice'];

// The default loader never touches the network: a remote reference fails.
try {
    JsonLd::expand($document);
} catch (JsonLdException $exception) {
    echo 'Default loader: ', $exception->errorCode()->value, "\n";
}

// MapContextLoader serves known contexts from memory and refuses the rest.
$loader = require __DIR__ . '/support.php';

echo 'Known context: ', json_encode(JsonLd::expand($document, new ProcessingOptions(loader: $loader)), JSON_UNESCAPED_SLASHES), "\n";

try {
    JsonLd::expand(['@context' => 'https://example.com/unknown'], new ProcessingOptions(loader: $loader));
} catch (JsonLdException $exception) {
    echo 'Unknown context: ', $exception->errorCode()->value, ' (', $exception->getPrevious()?->getMessage(), ")\n";
}

// Untrusted input: cap the size and the nesting of an inline JSON string.
try {
    JsonLd::expand(str_repeat('[', 100) . str_repeat(']', 100), new ProcessingOptions(maxDepth: 32, maxBytes: 65_536));
} catch (JsonLdException $exception) {
    echo 'Too deep: ', $exception->errorCode()->value, "\n";
}
