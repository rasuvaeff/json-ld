# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.0.0 — 2026-11-01

- Stable release. The public API and behaviour are those of 0.2.0, checked
  in the consumer pilot (cska-one, ecosystem-sandbox); from here on SemVer
  applies strictly.

## 0.2.0 — 2026-11-01

The pre-1.0 contract wave: every break below is deliberate, the API of this release is
meant to become 1.0 unchanged.

- **BC:** `ExpandOptions` is renamed `ProcessingOptions` (it configures every operation),
  and its constructor order is now `base, documentUrl, expandContext, loader, maxDepth,
  maxBytes`. Migration: rename the class; positional calls must switch to named arguments.
- Added `ProcessingOptions::$maxDepth` (default 512, the previous fixed cap) and
  `$maxBytes` (default no limit): guards for untrusted JSON strings, checked before and
  while decoding and during expansion (`document too large`, `document nested too deeply`).
  Depth now counts nested arrays and objects, as `json_decode()` does, not every value.
- **BC:** `JsonLd::document()` builds the node API from the node map (the structure
  flattening uses): embedded nodes without `@id` — a schema.org `author` — are now nodes,
  reachable through `Node::reference()`, and get blank node labels `_:b0`, `_:b1`, …;
  blank node labels of the input are relabeled the same way; nested `@graph`s become
  graphs of their own; a bare `{"@id": …}` reference is a node without properties.
  `Node::properties()`/`toArray()` hold references instead of embedded objects.
  Migration: follow `reference()` instead of reading embedded arrays; do not rely on
  input blank node labels.
- **BC:** `Graph\Document`'s constructor is private; use `Document::fromExpanded()`.
  `Graph\Graph` takes node objects in flattened form.
- Added `Node::valueObjects()` returning `Graph\Value` (value, language, datatype,
  direction): language-tagged values no longer lose their tag. `Node::values()` and
  friends accept single-valued members such as `@index`.
- **BC:** whatever a `ContextLoaderInterface` throws now surfaces as
  `DocumentLoadingException` with the loader's exception as the previous one:
  `loading remote context failed` for contexts (it was an `InvalidContextException`,
  and non-`DocumentLoadingException` throwables escaped unwrapped), `loading document failed`
  for a document URL (inside a term's scoped `@context` the W3C algorithm still reports
  `invalid scoped context`, now with the `DocumentLoadingException` as previous).
  Migration: catch `DocumentLoadingException`/`JsonLdException`
  instead of `InvalidContextException` or `RuntimeException`.
- **BC:** exceptions carry an error code: `JsonLdException::errorCode(): ErrorCode` (W3C
  JSON-LD 1.1 API codes plus `recursive context inclusion` and four processor codes:
  `document nested too deeply`, `document too large`, `invalid N-Quads`,
  `canonicalization limit exceeded`). The constructor is
  `(ErrorCode $errorCode, ?string $message = null, ?Throwable $previous = null)`; the
  message defaults to the code. `InvalidJsonException` reports `loading document failed`
  with the JSON error as its message. Migration: branch on `errorCode()`, not on the
  message; custom loaders that throw `DocumentLoadingException` pass a code (or throw
  anything — it is wrapped).
- **BC:** `Dataset::canonicalHash()` now implements W3C RDF Dataset Canonicalization
  RDFC-1.0 (SHA-256 of the canonical N-Quads) instead of a home-grown algorithm, so hash
  values change; `Dataset::equals()` compares canonical N-Quads. Migration: recompute
  stored hashes.
- Added `Dataset::canonicalNQuads()`, `Rdf\Canonicalizer` (hash algorithm, work factor),
  `Rdf\CanonicalForm` (canonical N-Quads, issued identifiers, `hash()`),
  `Rdf\HashAlgorithm` (SHA-256, SHA-384) and `Exception\CanonicalizationLimitException`:
  poison graphs stop after `workFactor × n` Hash N-Degree Quads runs per blank node
  (default factor 4). The W3C rdf-canon suite passes 86 of 86 (64 evaluation, 21 map,
  1 negative), nothing skipped. Documented limitation of the specification: blank nodes that
  differ only in which named graph their relations live in tie, and the input quad order
  decides between equally valid canonical forms.
- **BC:** `rdfDirection` of `JsonLd::toRdf()`/`fromRdf()` is the enum `RdfDirection`
  (`I18nDatatype`, `CompoundLiteral`). Migration: `'i18n-datatype'` →
  `RdfDirection::I18nDatatype`, `'compound-literal'` → `RdfDirection::CompoundLiteral`.
- **BC:** the RDF vocabulary constants moved from `Rdf\Literal` to `Rdf\Vocabulary`
  (`Literal::XSD_STRING` → `Vocabulary::XSD_STRING`, likewise `RDF_LANGSTRING`, `RDF_JSON`,
  `RDF_FIRST`, `RDF_REST`, `RDF_NIL`, `RDF_TYPE`; new: `XSD_BOOLEAN`, `XSD_INTEGER`,
  `XSD_DOUBLE`, `RDF_LIST`, `RDF_VALUE`, `RDF_LANGUAGE`, `RDF_DIRECTION`, `I18N`).
- **BC:** `Dataset::quads()` is removed; read the `Dataset::$quads` property, as on the
  other RDF value objects.
- **BC:** internal methods left the public classes: `Expander::expandRaw()` and
  `NQuads::formatTerm()` are gone, `Flattener` no longer takes an `Expander`.
- Added `Loader\MapContextLoader`: a fixed URL → document map, exact match, no I/O.
- README states the scope (no compaction or framing in 1.0) and that the package reads
  JSON-LD rather than emitting schema.org markup.

## 0.1.0 — 2026-10-23

- JSON-LD 1.1 expansion, flattening, node API, RDF conversion (`toRdf`, `fromRdf`) and N-Quads.
- W3C JSON-LD API test suite: expand 368/368, flatten 54/55 (one `compactArrays` case skipped), toRdf 447/448 (one empty-object JSON literal case skipped), fromRdf 53/53. Compaction, framing and the 1.0 processing mode are not implemented.
- Context loading only through an injected `ContextLoaderInterface`; the default `InlineContextLoader` refuses URLs, `PsrContextLoader` takes a URL policy closure and a body size limit.
- Checked on 14 JSON-LD blocks collected from real pages (Wikipedia, YouTube, BBC, The New York Times, IMDb, Kommersant, Championat and others): expansion and flattening give the same RDF, N-Quads and `fromRdf` round-trip.
- Mutation score 98.3% (`minMsi` 92).
