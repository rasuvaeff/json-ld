<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests;

use Rasuvaeff\JsonLd\Exception\JsonLdException;
use Rasuvaeff\JsonLd\Expander;
use Rasuvaeff\JsonLd\ExpandOptions;
use Rasuvaeff\JsonLd\Json;
use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\Keywords;
use Rasuvaeff\JsonLd\Tests\Support\FixtureContextLoader;
use Rasuvaeff\JsonLd\Tests\Support\W3cManifest;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
#[Covers(Expander::class)]
final class W3cExpandTest
{
    /**
     * Features not implemented yet; each entry disappears when the feature
     * lands, and the suite grows accordingly.
     *
     * @var array<string, string>
     */
    private const array SKIPPED = [];

    #[DataProvider('suiteProvider')]
    public function case(string $name, mixed $input, mixed $expected, ?string $expectedError, ?string $base, mixed $expandContext): void
    {
        $options = new ExpandOptions(
            base: $base,
            expandContext: $expandContext,
            loader: new FixtureContextLoader(),
        );

        if ($expectedError !== null) {
            $message = null;

            try {
                JsonLd::expand(is_string($input) ? $input : (array) $input, $options);
            } catch (JsonLdException $exception) {
                $message = $exception->getMessage();
            }

            Assert::same($message, $expectedError);

            return;
        }

        $result = JsonLd::expand(is_string($input) ? $input : (array) $input, $options);

        Assert::same(self::canonical($result), self::canonical($expected));
    }

    public static function suiteProvider(): iterable
    {
        foreach ((new W3cManifest())->cases('expand') as $name => $case) {
            if (isset(self::SKIPPED[$name])) {
                continue;
            }

            yield $name => [
                $name,
                $case['input'],
                $case['expected'],
                $case['expectedError'],
                $case['base'],
                $case['expandContext'],
            ];
        }
    }

    /**
     * Canonical JSON comparison: keys sorted recursively; lists keep their
     * order, except top-level node lists which the suite treats as
     * unordered.
     */
    public static function canonical(mixed $value): string
    {
        return Json::encode(self::sorted($value, ordered: false));
    }

    private static function sorted(mixed $value, bool $ordered): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            $items = array_map(static fn(mixed $item): mixed => self::sorted($item, ordered: false), $value);

            if (!$ordered) {
                usort($items, static fn(mixed $a, mixed $b): int => strcmp(Json::encode($a), Json::encode($b)));
            }

            return $items;
        }

        $result = [];

        foreach ($value as $key => $item) {
            $result[(string) $key] = self::sorted($item, ordered: (string) $key === Keywords::LIST);
        }

        ksort($result);

        return $result;
    }
}
