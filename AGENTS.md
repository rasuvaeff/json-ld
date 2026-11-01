# AGENTS.md — json-ld

Guidance for AI agents working on this package. Read before changing code.

## What this is

JSON-LD 1.1 processor for PHP: expand, flatten, node API, RDF, N-Quads and
RDF Dataset Canonicalization (RDFC-1.0). Namespace: `Rasuvaeff\JsonLd`
(PSR-4: `src/`). The public API turns compact JSON-LD documents into
expanded/flattened forms, exposes the node API (built from the node map) and
converts to RDF datasets serialized as N-Quads, with a canonical form and
hash. It reads JSON-LD; it is not a schema.org emitter (that is
`rasuvaeff/yii3-seo`). Scope for 1.0: no compaction, no framing.

## Golden rules

1. **Verification is mandatory.** Never claim "done" without a fresh green
   `composer build`. "Should work" does not count.
2. **No suppressions.** No `@psalm-suppress`, no baseline. Fix the root cause.
3. **No implicit I/O.** The library must not perform implicit I/O: remote
   context loading happens only through an explicitly injected
   `ContextLoaderInterface` implementation; the default loader never touches
   network or filesystem beyond parsing in-memory data.
4. **Preserve the public contract.** Update README + tests with any API change.

## Agent skill

The package ships `resources/skills/rasuvaeff-json-ld/SKILL.md` (distributed via
`extra.skills`); keep its safety rules in sync with the code.

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

- W3C suite (`tests/fixtures/w3c`): the `Unit` suite runs expand, flatten, toRdf and
  fromRdf; JSON-LD 1.0 cases (`processingMode`/`specVersion` json-ld-1.0) are not run
  (see `W3cManifest`), the remaining skips live in the `SKIPPED` constants of the
  `W3c*Test` classes with their reasons. The harnesses compare
  `errorCode()->value` with `expectErrorCode`.
- RDFC-1.0 suite (`tests/fixtures/rdf-canon`, copied unchanged from w3c/rdf-canon at
  the commit named in its `LICENSE.md`): `W3cRdfCanonTest` runs all 86 entries
  (evaluation, map, negative), nothing skipped. Keep it at 86/86.
- **Error codes are the contract.** Every exception carries an `ErrorCode`; W3C
  codes for JSON-LD failures, the four processor codes for the rest. New failure →
  pick an existing W3C code or add a processor case, never a bare message.
  `DocumentExpansion::run()` and `ContextProcessor::load()` wrap **every**
  `Throwable` from a loader in `DocumentLoadingException`; keep it that way. The one
  place it is re-wrapped is scoped-context validation (W3C Create Term Definition:
  any error → `invalid scoped context`), tested in `LoaderFailureTest`.
- **Canonicalization work limit.** `Canonicalizer::DEFAULT_WORK_FACTOR` (4) is
  calibrated: W3C test044–046 need 43 Hash N-Degree Quads runs for one of 12
  non-unique blank nodes (factor 3.6), the 10-node clique (test074) must fail fast,
  cycles and lists of equal values need about 1. Lowering it below 4 breaks the
  suite; raising it makes poison graphs more expensive (`factor × n²` calls).
- **RDFC-1.0 named-graph ties are a spec limitation, not a bug to fix here.** Hash
  Related Blank Node ignores graph names, so `CanonicalizerTest::knownLimitationQuadOrderBreaksNamedGraphTies`
  shows two quad orders of one dataset with different canonical forms. Do not "fix" it by
  hashing the graph name: hashes would stop matching every other RDFC-1.0 implementation.
  The order-invariance property is therefore tested on triples only
  (`canonicalFormOfTriplesIgnoresStatementOrder`); relabelling is tested with graphs.
- `Graph\Document` is built from `NodeMap` (same as flatten): blank node labels are
  issued by the node map, so they change whenever node-map traversal order changes —
  that is a visible behaviour change, note it in the CHANGELOG.
- Remote context loading is opt-in: the processor receives documents as
  in-memory data; anything hitting the network goes through an explicitly
  injected `ContextLoaderInterface` and never happens as a side effect of
  `expand()`/`flatten()`/RDF conversion.
- **Original implementation, not a port.** No code is copied from
  `ml/json-ld` or `embed/embed` (owner's decision) — they serve as
  behavioral reference only. The source of algorithms is the open specs:
  W3C JSON-LD 1.1 / Processing Algorithms, RFC 3986 (§5 IRI resolution),
  RDF 1.1 N-Quads, W3C RDF Dataset Canonicalization (RDFC-1.0), IANA
  special-purpose address registries. Only the W3C test suites (JSON-LD API,
  rdf-canon) are vendored, as test data under their own license.
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
