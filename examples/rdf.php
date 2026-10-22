<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Rasuvaeff\JsonLd\ExpandOptions;
use Rasuvaeff\JsonLd\JsonLd;

$loader = require __DIR__ . '/support.php';

$dataset = JsonLd::toRdf(require __DIR__ . '/article.php', new ExpandOptions(loader: $loader));
$nquads = JsonLd::serializeNQuads($dataset);

echo $nquads, "\n";

$parsed = JsonLd::parseNQuads($nquads);

echo 'Round trip equal: ', $parsed->equals($dataset) ? 'yes' : 'no', "\n";
echo 'Nodes after fromRdf: ', count(JsonLd::fromRdf($parsed)), "\n";
