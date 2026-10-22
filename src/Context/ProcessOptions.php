<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Context;

use Rasuvaeff\JsonLd\Iri;
use Rasuvaeff\JsonLd\Loader\ContextLoaderInterface;

/**
 * Options carried through one context processing run.
 *
 * @internal
 */
final readonly class ProcessOptions
{
    /**
     * @param list<string> $remoteContexts remote context locations already being loaded, for cycle detection
     */
    public function __construct(
        public ContextLoaderInterface $loader,
        public ?Iri $base = null,
        public bool $overrideProtected = false,
        public bool $propagate = true,
        public array $remoteContexts = [],
        public int $depth = 0,
        public bool $scoped = false,
    ) {}

    public function withBase(?Iri $base): self
    {
        return new self($this->loader, $base, $this->overrideProtected, $this->propagate, $this->remoteContexts, $this->depth, $this->scoped);
    }

    public function withScoped(): self
    {
        return new self($this->loader, $this->base, $this->overrideProtected, $this->propagate, $this->remoteContexts, $this->depth, true);
    }

    public function withoutPropagation(): self
    {
        return new self($this->loader, $this->base, $this->overrideProtected, false, $this->remoteContexts, $this->depth, $this->scoped);
    }

    public function withRemoteContext(string $url): self
    {
        return new self($this->loader, $this->base, $this->overrideProtected, $this->propagate, [...$this->remoteContexts, $url], $this->depth, $this->scoped);
    }

    public function deeper(): self
    {
        return new self($this->loader, $this->base, $this->overrideProtected, $this->propagate, $this->remoteContexts, $this->depth + 1, $this->scoped);
    }
}
