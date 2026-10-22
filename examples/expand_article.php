<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Rasuvaeff\JsonLd\ExpandOptions;
use Rasuvaeff\JsonLd\JsonLd;

$loader = require __DIR__ . '/support.php';

$expanded = JsonLd::expand(require __DIR__ . '/article.php', new ExpandOptions(loader: $loader));

echo json_encode($expanded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
