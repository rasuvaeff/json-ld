<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\ProcessingOptions;

$loader = require __DIR__ . '/support.php';

$expanded = JsonLd::expand(require __DIR__ . '/article.php', new ProcessingOptions(loader: $loader));

echo json_encode($expanded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
