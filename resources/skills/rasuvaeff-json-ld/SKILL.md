---
name: rasuvaeff-json-ld
description: >-
  JSON-LD 1.1 processing in PHP with rasuvaeff/json-ld — JsonLd::expand,
  flatten, toRdf and fromRdf, ExpandOptions (base, documentUrl,
  expandContext, loader), Iri (RFC 3986 resolution), ContextLoaderInterface
  with InlineContextLoader (default, no I/O) and PsrContextLoader (PSR-18,
  URL policy and size limit), the node API (JsonLd::document, Graph, Node),
  NQuads and Dataset (isomorphism-aware equals), and the JsonLdException
  family whose message is the W3C error code. Use when writing, reviewing or
  debugging code that reads schema.org / JSON-LD markup, converts it to RDF
  or N-Quads, or loads remote contexts, in a project that has this package
  installed.
---

# rasuvaeff/json-ld

JSON-LD 1.1 processor: expansion, flattening, node API, RDF (N-Quads).
Namespace `Rasuvaeff\JsonLd\`. Passes the W3C JSON-LD API test suite for
expand, flatten, toRdf and fromRdf (compaction, framing and JSON-LD 1.0
processing mode are not implemented).

## Safety rules — verify these on every change

1. **No implicit I/O.** `JsonLd::expand()` never fetches anything by itself.
   A remote `@context` URL, `@import` or a string document reference goes only
   through the `ContextLoaderInterface` passed in `ExpandOptions(loader: …)`.
   The default `InlineContextLoader` refuses every URL, and a document that
   references a remote context then fails with `loading remote context failed`.

2. **Treat documents as untrusted, and so their contexts.** A hostile page can
   point `@context` at an internal address (SSRF) or at a huge file. When you
   inject `PsrContextLoader`, always pass a URL policy (allow-list of hosts,
   https only, no private ranges) and keep `maxBytes` small; configure
   timeouts and redirect handling on the PSR-18 client itself. Better still:
   serve known contexts (`https://schema.org/`) from a local cache loader and
   refuse everything else.

3. **Bounded input.** Expansion stops at 512 nesting levels
   (`document nested too deeply`) and at 64 chained remote contexts
   (`context overflow`). Cap the raw document size yourself before decoding it
   (`json_decode` of an unbounded body is your responsibility), and catch
   `JsonLdException` — never let one bad block take down a whole page.

4. **JSON-LD 1.0 mode is not supported.** There is no `processingMode`
   option; documents that rely on 1.0-only behaviour are processed as 1.1
   (for example `@version: 1.1` is the only accepted value). If you need 1.0
   results, pre-process the input or use another library.

5. **Error codes are the contract.** Every `JsonLdException` message is the
   W3C error code (`invalid @id value`, `colliding keywords`, …). Branch on
   the message, not on a free-text description.

6. **No bare `{}` identity.** Documents are PHP arrays, so an empty JSON
   object and an empty array are the same value; an empty object inside a
   `@json` literal serializes as `[]`.

## Canonical usage

```php
use Rasuvaeff\JsonLd\ExpandOptions;
use Rasuvaeff\JsonLd\Exception\JsonLdException;
use Rasuvaeff\JsonLd\JsonLd;

$options = new ExpandOptions(base: 'https://example.com/page', loader: $cachedLoader);

try {
    $expanded = JsonLd::expand($jsonString, $options);   // list of node/value objects
    $flat     = JsonLd::flatten($jsonString, $options);  // nodes by @id, blank nodes _:b0…
    $quads    = JsonLd::serializeNQuads(JsonLd::toRdf($jsonString, $options));
} catch (JsonLdException $e) {
    // $e->getMessage() is the W3C error code
}
```

Node API over the expanded form:

```php
$graph = JsonLd::document($data, $options)->defaultGraph();

foreach ($graph->byType('https://schema.org/Article') as $node) {
    $node->value('https://schema.org/headline');             // first scalar value
    $node->reference('https://schema.org/author')?->id();    // follow a node reference
}
```

N-Quads and datasets:

```php
$dataset = JsonLd::parseNQuads($text);          // comments and blank lines allowed
$dataset->equals($other);                       // equal up to blank-node renaming
JsonLd::fromRdf($dataset, useNativeTypes: true);
```

Replacing the loader in tests:

```php
final class StaticLoader implements ContextLoaderInterface
{
    public function load(string $reference): LoadedContext
    {
        return new LoadedContext(
            documentUrl: $reference,
            document: ['@context' => ['@vocab' => 'https://schema.org/']],
        );
    }
}
```

## Pitfalls

- `expand()` accepts a string: inline JSON (starting with `{` or `[`) is
  decoded directly, anything else is handed to the loader as a document URL.
- `ExpandOptions::$base` redirects relative IRI resolution; relative context
  references resolve against `documentUrl` (or `base` when it is absent).
- Relative IRIs are dropped from RDF output (`toRdf`), as the algorithm
  requires; blank node labels in `toRdf`/`flatten` are generated and not stable
  across inputs — compare datasets with `Dataset::equals()`, not by text.
- `fromRdf` throws `invalid JSON literal` for an `rdf:JSON` literal that is
  not JSON.

## Full API

The complete reference ships with the package: read
`vendor/rasuvaeff/json-ld/llms.txt` before guessing a method name.
