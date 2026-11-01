---
name: rasuvaeff-json-ld
description: >-
  JSON-LD 1.1 processing in PHP with rasuvaeff/json-ld — JsonLd::expand,
  flatten, toRdf and fromRdf, ProcessingOptions (base, documentUrl,
  expandContext, loader, maxDepth, maxBytes), Iri (RFC 3986 resolution),
  ContextLoaderInterface with InlineContextLoader (default, no I/O),
  MapContextLoader (fixed URL map) and PsrContextLoader (PSR-18, URL policy
  and size limit), the node API (JsonLd::document, Graph, Node, Value),
  NQuads, Dataset and RDFC-1.0 canonicalization (Canonicalizer, canonicalHash,
  isomorphism-aware equals), and the JsonLdException family with
  errorCode(): ErrorCode. Use when writing, reviewing or
  debugging code that reads schema.org / JSON-LD markup, converts it to RDF
  or N-Quads, or loads remote contexts, in a project that has this package
  installed.
---

# rasuvaeff/json-ld

JSON-LD 1.1 processor: expansion, flattening, node API, RDF (N-Quads).
Namespace `Rasuvaeff\JsonLd\`. Passes the W3C JSON-LD API test suite for
expand, flatten, toRdf and fromRdf, and the W3C RDFC-1.0 suite (compaction,
framing and JSON-LD 1.0 processing mode are not implemented). It reads
JSON-LD; emitting schema.org markup is rasuvaeff/yii3-seo's job.

## Safety rules — verify these on every change

1. **No implicit I/O.** `JsonLd::expand()` never fetches anything by itself.
   A remote `@context` URL, `@import` or a string document reference goes only
   through the `ContextLoaderInterface` passed in `ProcessingOptions(loader: …)`.
   The default `InlineContextLoader` refuses every URL, and a document that
   references a remote context then fails with `loading remote context failed`.
   Whatever a loader throws reaches the caller as `DocumentLoadingException`
   (inside a term's scoped `@context`: `invalid scoped context`, the loading
   failure as previous).

2. **Treat documents as untrusted, and so their contexts.** A hostile page can
   point `@context` at an internal address (SSRF) or at a huge file. When you
   inject `PsrContextLoader`, always pass a URL policy (allow-list of hosts,
   https only, no private ranges) and keep `maxBytes` small; configure
   timeouts and redirect handling on the PSR-18 client itself. Better still:
   serve known contexts (`https://schema.org/`) from a `MapContextLoader` and
   refuse everything else.

3. **Bounded input.** Pass untrusted JSON as a string with
   `ProcessingOptions(maxBytes: …, maxDepth: …)`: the size is checked before
   decoding (`document too large`), the depth (default 512) while decoding and
   during expansion (`document nested too deeply`); remote context chains stop
   at 64 (`context overflow`). RDFC-1.0 canonicalization gives up on poison
   graphs (`CanonicalizationLimitException`). Catch `JsonLdException` — never
   let one bad block take down a whole page.

4. **JSON-LD 1.0 mode is not supported.** There is no `processingMode`
   option; documents that rely on 1.0-only behaviour are processed as 1.1
   (for example `@version: 1.1` is the only accepted value). If you need 1.0
   results, pre-process the input or use another library.

5. **Error codes are the contract.** Branch on
   `JsonLdException::errorCode()` (`ErrorCode::InvalidIdValue`,
   `ErrorCode::LoadingRemoteContextFailed`, …), never on the message.

6. **No bare `{}` identity.** Documents are PHP arrays, so an empty JSON
   object and an empty array are the same value; an empty object inside a
   `@json` literal serializes as `[]`.

## Canonical usage

```php
use Rasuvaeff\JsonLd\ProcessingOptions;
use Rasuvaeff\JsonLd\Exception\JsonLdException;
use Rasuvaeff\JsonLd\JsonLd;

$options = new ProcessingOptions(base: 'https://example.com/page', loader: $cachedLoader);

try {
    $expanded = JsonLd::expand($jsonString, $options);   // list of node/value objects
    $flat     = JsonLd::flatten($jsonString, $options);  // nodes by @id, blank nodes _:b0…
    $quads    = JsonLd::serializeNQuads(JsonLd::toRdf($jsonString, $options));
} catch (JsonLdException $e) {
    $e->errorCode();   // ErrorCode enum, ->value is the W3C code
}
```

Node API (built from the node map: embedded nodes without `@id` are nodes
labelled `_:b0`, `_:b1`…; input blank labels are relabeled):

```php
$graph = JsonLd::document($data, $options)->defaultGraph();

foreach ($graph->byType('https://schema.org/Article') as $node) {
    $node->value('https://schema.org/headline');             // first scalar value
    $node->reference('https://schema.org/author')?->value('https://schema.org/name');  // embedded author
    $node->valueObjects('https://schema.org/name');           // list<Value>: value, language, type, direction
}
```

N-Quads and datasets:

```php
$dataset = JsonLd::parseNQuads($text);          // comments and blank lines allowed
$dataset->equals($other);                       // RDFC-1.0 isomorphism
$dataset->canonicalHash();                      // SHA-256 of the canonical N-Quads
// RDFC-1.0 limitation: with named graphs, quad order can decide ties (see README)
JsonLd::fromRdf($dataset, useNativeTypes: true);
```

Serving known contexts without I/O:

```php
$loader = new MapContextLoader([
    'https://schema.org/' => ['@context' => ['@vocab' => 'https://schema.org/']],
]);   // exact match: add 'https://schema.org' too if documents use it
```

## Pitfalls

- `expand()` accepts a string: inline JSON (starting with `{` or `[`) is
  decoded directly, anything else is handed to the loader as a document URL.
- `ProcessingOptions::$base` redirects relative IRI resolution; relative context
  references resolve against `documentUrl` (or `base` when it is absent).
- Relative IRIs are dropped from RDF output (`toRdf`), as the algorithm
  requires; blank node labels in `toRdf`/`flatten`/`document` are generated and
  not stable across inputs — compare datasets with `Dataset::equals()`, not by text.
- `fromRdf` throws `invalid JSON literal` for an `rdf:JSON` literal that is
  not JSON.

## Full API

The complete reference ships with the package: read
`vendor/rasuvaeff/json-ld/llms.txt` before guessing a method name.
