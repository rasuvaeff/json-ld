<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd;

use Rasuvaeff\JsonLd\Loader\ContextLoaderInterface;

/**
 * Expansion-run state: the loader for context references plus the
 * inside-index / inside-list / inside-reverse flags of the current position
 * in the algorithm.
 *
 * @internal
 */
final readonly class ExpansionState
{
    public function __construct(
        public ContextLoaderInterface $loader,
        public bool $insideIndex = false,
        public bool $insideList = false,
        public bool $insideReverse = false,
    ) {}

    public function withInsideList(): self
    {
        return new self($this->loader, $this->insideIndex, true, $this->insideReverse);
    }

    public function withInsideIndex(): self
    {
        return new self($this->loader, true, $this->insideList, $this->insideReverse);
    }
}
