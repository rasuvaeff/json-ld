# AGENTS.md — json-ld

Guidance for AI agents working on this package. Read before changing code.

## What this is

JSON-LD 1.1 processor for PHP: expand, flatten, node API, RDF and N-Quads.
Namespace: `Rasuvaeff\JsonLd` (PSR-4: `src/`). The public API turns compact
JSON-LD documents into expanded/flattened forms, exposes the node API and
converts to RDF graphs serialized as N-Quads.

## Golden rules

1. **Verification is mandatory.** Never claim "done" without a fresh green
   `composer build`. "Should work" does not count.
2. **No suppressions.** No `@psalm-suppress`, no baseline. Fix the root cause.
3. **No implicit I/O.** The library must not perform implicit I/O: remote
   context loading happens only through an explicitly injected
   `ContextLoaderInterface` implementation; the default loader never touches
   network or filesystem beyond parsing in-memory data.
4. **Preserve the public contract.** Update README + tests with any API change.

## Commands

No PHP/Composer on the host — run in Docker via the `composer:2` image.

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer psalm
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
docker run --rm -v "$PWD":/app -w /app composer:2 composer release-check
```

Or with Make:

```bash
make build
make cs-fix
make psalm
make test
make test-coverage
make mutation
make release-check
```

`composer.lock` is gitignored (library).
`make test-coverage` and `make mutation` bootstrap `pcov` inside the
`composer:2` container because the base image has no coverage driver.

## Invariants & gotchas

- Remote context loading is opt-in: the processor receives documents as
  in-memory data; anything hitting the network goes through an explicitly
  injected `ContextLoaderInterface` and never happens as a side effect of
  `expand()`/`flatten()`/RDF conversion.
- **Original implementation, not a port.** No code is copied from
  `ml/json-ld` or `embed/embed` (owner's decision) — they serve as
  behavioral reference only. The source of algorithms is the open specs:
  W3C JSON-LD 1.1 / Processing Algorithms, RFC 3986 (§5 IRI resolution),
  RDF 1.1 N-Quads, IANA special-purpose address registries. Only the W3C
  test suite is vendored, as test data under its own license.
- Code: `declare(strict_types=1)`, `final readonly class`, `#[\Override]`,
  explicit types.
- **Validation patterns anchor with `\z`, never `$`.** In PCRE `$` also matches
  immediately before a trailing newline, so `/^…$/` silently accepts one `\n` —
  the value the whitelist was supposed to reject then travels on as if it were
  clean. `/D` does the same job. Cover it with a data-provider case carrying
  `"value\n"`, or the regression comes back unnoticed.
- `examples/` is part of the public contract: keep scripts runnable and update
  `examples/README.md` when example usage changes.
- **CI workflows are SHA-pinned.** Every `uses:` in `.github/workflows/*.yml`
  references a 40-char commit SHA with a `# vN` trailing comment
  (e.g. `actions/checkout@<sha> # v4`). Never revert to floating `@vN` tags.
  Updates go through Dependabot, which bumps the SHA and preserves the comment.
  Workflows also carry `permissions: { contents: read }` at workflow level and
  `persist-credentials: false` on every `actions/checkout` step. Verify with
  `zizmor --persona=auditor .github/` — must report no `unpinned-uses`,
  `excessive-permissions`, or `artipacked` findings.

## When you finish

- Update `README.md` **and `README.ru.md`** (both languages, same commit;
  and `examples/` if usage changed); update `CHANGELOG.md` when releasing.
- Re-run `composer build`; if the change affects public API or release safety,
  also run `make release-check`. Paste the output.
