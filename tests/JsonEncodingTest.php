<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Json;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(Json::class)]
final class JsonEncodingTest
{
    public function keepsSlashesAndUnicodeIntact(): void
    {
        Assert::same(Json::encode(['a/b' => 'é€']), '{"a/b":"é€"}');
    }

    public function failsLoudlyOnUnencodableValues(): void
    {
        $thrown = false;

        try {
            Json::encode("\xFF");
        } catch (\JsonException) {
            $thrown = true;
        }

        Assert::true($thrown);
    }
}
