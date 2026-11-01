<?php

declare(strict_types=1);

use Rasuvaeff\JsonLd\Loader\MapContextLoader;

// The schema.org context served from memory, so the examples run offline;
// every other remote context is refused.
return new MapContextLoader([
    'https://schema.org/' => ['@context' => ['@vocab' => 'https://schema.org/']],
]);
