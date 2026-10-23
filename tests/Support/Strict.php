<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests\Support;

/**
 * Runs a callback with every PHP diagnostic (warning, notice, deprecation)
 * turned into an exception, so a test fails on silently tolerated mistakes
 * such as undefined offsets.
 */
final readonly class Strict
{
    /**
     * @template T
     *
     * @param \Closure(): T $callback
     *
     * @return T
     */
    public static function run(\Closure $callback): mixed
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }
}
