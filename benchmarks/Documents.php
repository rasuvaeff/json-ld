<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Benchmarks;

/**
 * Fixture documents shared by the benchmarks.
 */
final class Documents
{
    /** Tolerance that turns the "current is fastest" verdict into a report. */
    public const float REPORT_ONLY = 1_000_000.0;

    /**
     * @return array<string, mixed>
     */
    public static function small(): array
    {
        return [
            '@context' => ['@vocab' => 'http://schema.org/', 'knows' => ['@type' => '@id']],
            '@id' => 'http://example.com/alice',
            '@type' => 'Person',
            'name' => 'Alice',
            'knows' => ['http://example.com/bob', 'http://example.com/carol'],
            'tags' => ['@list' => ['php', 'rdf', 'json-ld']],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function large(): array
    {
        $graph = [];

        for ($i = 0; $i < 200; $i++) {
            $graph[] = [
                '@id' => 'http://example.com/person/' . $i,
                '@type' => 'Person',
                'name' => 'Person ' . $i,
                'knows' => ['http://example.com/person/' . (($i + 1) % 200)],
                'address' => ['@type' => 'PostalAddress', 'city' => 'City ' . $i],
                'tags' => ['@list' => ['a', 'b', 'c']],
            ];
        }

        return [
            '@context' => ['@vocab' => 'http://schema.org/', 'knows' => ['@type' => '@id']],
            '@graph' => $graph,
        ];
    }

    public static function floor(): string
    {
        return json_encode(self::small(), JSON_THROW_ON_ERROR);
    }
}
