<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests\Support;

use Psr\Http\Client\ClientExceptionInterface;

final class StubClientException extends \RuntimeException implements ClientExceptionInterface {}
