<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Rasuvaeff\JsonLd\Exception\CanonicalizationLimitException;
use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\Rdf\Canonicalizer;
use Rasuvaeff\JsonLd\Rdf\HashAlgorithm;

// The same graph, written with other blank node labels and in another order.
$left = JsonLd::parseNQuads("_:a <http://schema.org/knows> _:b .\n_:b <http://schema.org/name> \"Bob\" .\n");
$right = JsonLd::parseNQuads("_:y <http://schema.org/name> \"Bob\" .\n_:x <http://schema.org/knows> _:y .\n");

echo $left->canonicalNQuads();
echo 'Equal: ', $left->equals($right) ? 'yes' : 'no', "\n";
echo 'SHA-256: ', $left->canonicalHash(), "\n";

$form = (new Canonicalizer(hashAlgorithm: HashAlgorithm::Sha384))->canonicalize($right);

echo 'SHA-384: ', $form->hash(), "\n";

foreach ($form->issuedIdentifiers as $input => $canonical) {
    echo '  ', $input, ' => ', $canonical, "\n";
}

// A clique of blank nodes needs factorial work: the canonicalizer gives up.
$clique = '';

foreach (range(0, 7) as $from) {
    foreach (range(0, 7) as $to) {
        $clique .= $from === $to ? '' : sprintf("_:n%d <http://p> _:n%d .\n", $from, $to);
    }
}

try {
    JsonLd::parseNQuads($clique)->canonicalHash();
} catch (CanonicalizationLimitException $exception) {
    echo 'Clique: ', $exception->getMessage(), "\n";
}
