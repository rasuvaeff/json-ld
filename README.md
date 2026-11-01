# rasuvaeff/json-ld

[![Latest Stable Version](https://poser.pugx.org/rasuvaeff/json-ld/v)](https://packagist.org/packages/rasuvaeff/json-ld)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/json-ld/downloads)](https://packagist.org/packages/rasuvaeff/json-ld)
[![Build](https://github.com/rasuvaeff/json-ld/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/json-ld/actions/workflows/build.yml)
[![Static analysis](https://github.com/rasuvaeff/json-ld/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/json-ld/actions/workflows/static-analysis.yml)
[![Psalm level](https://img.shields.io/badge/psalm-level_1-blue.svg)](https://github.com/rasuvaeff/json-ld/actions/workflows/static-analysis.yml)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/json-ld/php)](https://packagist.org/packages/rasuvaeff/json-ld)
[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE.md)

JSON-LD 1.1 processor for PHP: expansion, flattening, a node API, RDF conversion,
N-Quads and W3C RDF Dataset Canonicalization (RDFC-1.0).

This package **reads** JSON-LD: it turns documents into their expanded/flattened form,
a graph of nodes or RDF. It is not a schema.org markup emitter — to write
`<script type="application/ld+json">` for your pages use
[rasuvaeff/yii3-seo](https://github.com/rasuvaeff/yii3-seo).

> Using an AI coding assistant? [llms.txt](llms.txt) contains a compact API reference you can share with the model.

## Scope

| Supported (the 1.0 contract) | Not implemented |
|---|---|
| Expansion, flattening, the node API (`Graph\Document`) | Compaction |
| `toRdf` / `fromRdf`, N-Quads | Framing |
| RDF Dataset Canonicalization RDFC-1.0 (`Rdf\Canonicalizer`) | JSON-LD 1.0 processing mode |

Compaction and framing are out of scope for 1.0.

## Requirements

- PHP 8.3–8.5 (8.6 is checked in CI as experimental)
- `ext-json`, `ext-mbstring`
- `psr/http-client` ^1.0, `psr/http-factory` ^1.0, `psr/http-message` ^1.0 || ^2.0

## Installation

```bash
composer require rasuvaeff/json-ld
```

## Usage

```php
use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\Loader\MapContextLoader;
use Rasuvaeff\JsonLd\ProcessingOptions;

$options = new ProcessingOptions(
    base: 'https://example.com/page',
    loader: new MapContextLoader(['https://schema.org/' => ['@context' => ['@vocab' => 'https://schema.org/']]]),
);

$expanded = JsonLd::expand($document, $options);   // list of node/value objects
$flat     = JsonLd::flatten($document, $options);  // nodes keyed by @id, blank nodes relabeled _:b0, _:b1, ...
$graph    = JsonLd::document($document, $options)->defaultGraph();
$dataset  = JsonLd::toRdf($document, $options);    // Dataset of quads
$nquads   = JsonLd::serializeNQuads($dataset);
$back     = JsonLd::fromRdf(JsonLd::parseNQuads($nquads));
$hash     = $dataset->canonicalHash();             // SHA-256 of the RDFC-1.0 canonical N-Quads
```

`$document` is a decoded array or a JSON string: inline JSON (starting with `{` or `[`)
is decoded, any other string is a document URL handed to the loader.

### Classes

| Class | Purpose |
|---|---|
| `JsonLd` | Facade: `expand`, `flatten`, `document`, `toRdf`, `fromRdf`, `serializeNQuads`, `parseNQuads` |
| `ProcessingOptions` | `base`, `documentUrl`, `expandContext`, `loader`, `maxDepth` (default 512), `maxBytes` (default no limit) |
| `RdfDirection` | `rdfDirection` of `toRdf`/`fromRdf`: `I18nDatatype`, `CompoundLiteral` |
| `Expander`, `Flattener` | The algorithms behind the facade |
| `Iri` | RFC 3986 parsing and relative reference resolution |
| `Loader\ContextLoaderInterface`, `LoadedContext` | How remote contexts and documents are fetched |
| `Loader\InlineContextLoader` | The default: parses inline JSON, refuses every URL, no I/O |
| `Loader\MapContextLoader` | Fixed map URL → decoded document, exact match, no I/O |
| `Loader\PsrContextLoader` | PSR-18 client, URL policy closure, body size limit |
| `Graph\Document`, `Graph`, `Node`, `Value` | Node API (below) |
| `Rdf\Dataset`, `Quad`, `Iri`, `BlankNode`, `Literal`, `NQuads` | RDF model and N-Quads |
| `Rdf\Vocabulary` | IRIs of the RDF/XSD terms (`RDF_TYPE`, `XSD_STRING`, `RDF_LANGSTRING`, ...) |
| `Rdf\Canonicalizer`, `CanonicalForm`, `HashAlgorithm` | RDFC-1.0 (below) |
| `Exception\JsonLdException`, `ErrorCode` | Errors (below) |

### Node API

`JsonLd::document()` builds the graphs from the node map — the structure flattening
uses — so every node object of the document is a node of its graph, embedded ones
included, and embedded objects become references:

```php
$article = $graph->node('https://example.com/articles/1');
$author  = $article?->reference('https://schema.org/author');   // embedded, no @id: still reachable
$author?->id();                                                 // "_:b0"
$author?->value('https://schema.org/name');                     // "Alice"

foreach ($node->valueObjects('https://schema.org/name') as $name) {
    $name->value; $name->language; $name->type; $name->direction;
}
```

- Nodes without `@id` get blank node labels `_:b0`, `_:b1`, … in the order the algorithm
  meets them; blank node labels of the input are relabeled the same way. Labels are
  stable for the same input, but they are not the labels the input used. IRIs are kept.
- Nodes come in document order. A bare reference (`{"@id": "…"}` that nothing describes)
  is a node without properties, so `reference()` resolves it.
- `Node`: `id()`, `types()`, `hasType()`, `properties()`, `values()` (scalars),
  `value()`, `valueObjects()` (`Graph\Value` with language, datatype and direction),
  `references()`, `reference()`, `toArray()` (the flattened node object).
- `Graph`: `nodes()`, `node($id)`, `byType($iri)`; `Document`: `defaultGraph()`,
  `graph($name)`, `graphs()`, `Document::fromExpanded($expanded)`.

### RDF Dataset Canonicalization

`Rdf\Canonicalizer` implements [RDFC-1.0](https://www.w3.org/TR/rdf-canon/): two
datasets have the same canonical N-Quads exactly when they are isomorphic.

```php
use Rasuvaeff\JsonLd\Rdf\Canonicalizer;
use Rasuvaeff\JsonLd\Rdf\HashAlgorithm;

$dataset->canonicalNQuads();   // sorted canonical N-Quads, blank nodes _:c14n0, _:c14n1, ...
$dataset->canonicalHash();     // hex SHA-256 of canonicalNQuads()
$dataset->equals($other);      // isomorphism: same canonical N-Quads

$form = (new Canonicalizer(hashAlgorithm: HashAlgorithm::Sha384, workFactor: 4))->canonicalize($dataset);
$form->nquads;                 // canonical N-Quads
$form->issuedIdentifiers;      // ['_:e0' => '_:c14n0', ...] input label => canonical label
$form->hash();                 // digest with the same algorithm
```

**Poison graphs.** Some datasets (cliques of blank nodes) need factorial work. With `n`
blank nodes that share their first-degree hash, Hash N-Degree Quads may run at most
`workFactor × n` times per blank node (default `workFactor` 4), which bounds a run to
`workFactor × n²` calls; past that `Exception\CanonicalizationLimitException` is thrown
(error code `canonicalization limit exceeded`). Legitimate symmetric shapes — a cycle,
a list of equal values — need about `n` runs per blank node. `canonicalNQuads()`,
`canonicalHash()` and `equals()` can throw it too.

### Errors

Every exception extends `Exception\JsonLdException`; `errorCode()` returns an
`Exception\ErrorCode` and is the contract to branch on. The message is for people: it
equals the error code unless the processor has details to add (a loader failure, a JSON
syntax error).

| Exception | Error codes |
|---|---|
| `InvalidContextException` | W3C context codes: `invalid term definition`, `invalid remote context`, … |
| `InvalidInputException` | W3C document codes (`invalid @id value`, …); `document nested too deeply`, `document too large` |
| `ContextRecursionException` | `context overflow`, `recursive context inclusion` |
| `DocumentLoadingException` | `loading remote context failed`, `loading document failed` |
| `InvalidJsonException` | `loading document failed` (inline JSON that is not JSON or not an object/array) |
| `InvalidNQuadsException` | `invalid N-Quads` |
| `CanonicalizationLimitException` | `canonicalization limit exceeded` |

Four cases are this processor's own: `document nested too deeply`, `document too large`,
`invalid N-Quads`, `canonicalization limit exceeded`. `recursive context inclusion` is a
JSON-LD 1.0 code the W3C test suite still expects; every other case is a W3C JSON-LD 1.1
API error code.

**Whatever a loader throws** — a `DocumentLoadingException`, a PSR-18 exception, any
`Throwable` — reaches the caller as a `DocumentLoadingException`: `loading remote
context failed` for a context, `loading document failed` for the document itself, with
the loader's exception as `getPrevious()`.

## Security

- **No implicit I/O.** The default loader never touches the network or the filesystem:
  a remote `@context` fails with `loading remote context failed` unless you inject a
  `ContextLoaderInterface`. Prefer `MapContextLoader` for the contexts you know.
- `PsrContextLoader` takes an optional URL policy (`Closure(string): bool`) and a body size
  limit (default 1 MiB). Treat documents as untrusted: allow-list hosts, refuse private
  ranges, set timeouts on the PSR-18 client.
- **Untrusted JSON strings.** `ProcessingOptions(maxBytes: …)` caps the length of an inline
  JSON string before it is decoded (`document too large`); `maxDepth` (default 512) caps
  nesting both when the string is decoded and during expansion
  (`document nested too deeply`). Remote context chains stop at 64 (`context overflow`).
- **RDFC-1.0** is bounded by the work factor (see above).

## Examples

Runnable scripts (no network) in [examples/](examples/):

| Script | Shows |
|---|---|
| [`expand_article.php`](examples/expand_article.php) | expanding a schema.org Article/VideoObject `@graph` |
| [`context_features.php`](examples/context_features.php) | prefixes, `@vocab`, keyword aliases, `@container` |
| [`loaders.php`](examples/loaders.php) | the default loader refuses URLs; `MapContextLoader`; error codes; input limits |
| [`flatten_node_api.php`](examples/flatten_node_api.php) | `flatten`, the node API, embedded nodes, language-tagged values |
| [`rdf.php`](examples/rdf.php) | `toRdf`, N-Quads, `fromRdf` round trip |
| [`canonicalize.php`](examples/canonicalize.php) | RDFC-1.0: canonical N-Quads, hashes, issued labels, the poison-graph limit |
| [`html_script.php`](examples/html_script.php) | extracting `application/ld+json` blocks from a page |

Examples are expected to execute without fatal errors and stay aligned with the
documented public API.

## AI agent skill

The package ships an agent skill: [`resources/skills/rasuvaeff-json-ld/SKILL.md`](resources/skills/rasuvaeff-json-ld/SKILL.md)
(distributed through `extra.skills`).

## Conformance

- The W3C JSON-LD API test suite (expand, flatten, toRdf, fromRdf) is vendored under
  `tests/fixtures/w3c` and run in full, except tests of the 1.0 processing mode and one
  compaction-dependent case. The test data belongs to the W3C and is used under the
  [W3C Test Suite License and 3-clause BSD License](tests/fixtures/w3c/LICENSE.md)
  (Copyright W3C and the JSON-LD Working Group).
- The W3C RDF Dataset Canonicalization test suite is vendored under
  `tests/fixtures/rdf-canon` and passes in full: 64 evaluation tests (SHA-256 and SHA-384),
  21 issued-identifier map tests and the poison-graph negative test, 86 of 86, none skipped
  ([license](tests/fixtures/rdf-canon/LICENSE.md), Copyright W3C).

## Development

No PHP/Composer on the host — run in Docker via the `composer:2` image:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer install
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
docker run --rm -v "$PWD":/app -w /app composer:2 composer release-check
```

Or with Make:

```bash
make install
make build
make cs-fix
make test
make test-coverage
make mutation
make release-check
```

`make test-coverage` and `make mutation` bootstrap `pcov` inside the
`composer:2` container because the base image has no coverage driver.

## License

[BSD-3-Clause](LICENSE.md)
