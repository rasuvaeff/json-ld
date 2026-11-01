<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Rdf;

/**
 * Identifier Issuer, RDFC-1.0 section 4.3: issues `<prefix><counter>`
 * identifiers and remembers the order in which it issued them.
 *
 * Blank node labels are kept with their `_:` prefix, so that labels such as
 * `_:0` never turn into integer array keys.
 *
 * @internal
 */
final class IdentifierIssuer
{
    /** @var array<string, string> existing label => issued label, in issue order */
    private array $issued = [];

    private int $counter = 0;

    public function __construct(private readonly string $prefix) {}

    public function issue(string $label): string
    {
        return $this->issued[$label] ??= '_:' . $this->prefix . $this->counter++;
    }

    public function issued(string $label): ?string
    {
        return $this->issued[$label] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->issued;
    }
}
