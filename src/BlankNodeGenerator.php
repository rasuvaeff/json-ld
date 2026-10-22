<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd;

/**
 * Generates unique blank node identifiers within one processing run.
 *
 * @internal
 */
final class BlankNodeGenerator
{
    private int $counter = 0;

    /**
     * @return string non-empty blank node identifier like `_:b12`
     */
    public function next(): string
    {
        return '_:b' . $this->counter++;
    }
}
