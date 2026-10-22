# rasuvaeff/json-ld

[![Latest Stable Version](https://poser.pugx.org/rasuvaeff/json-ld/v)](https://packagist.org/packages/rasuvaeff/json-ld)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/json-ld/downloads)](https://packagist.org/packages/rasuvaeff/json-ld)
[![Build](https://github.com/rasuvaeff/json-ld/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/json-ld/actions/workflows/build.yml)
[![Static analysis](https://github.com/rasuvaeff/json-ld/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/json-ld/actions/workflows/static-analysis.yml)
[![Psalm level](https://img.shields.io/badge/psalm-level_1-blue.svg)](https://github.com/rasuvaeff/json-ld/actions/workflows/static-analysis.yml)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/json-ld/php)](https://packagist.org/packages/rasuvaeff/json-ld)
[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE.md)

JSON-LD 1.1 processor for PHP: expand, flatten, node API, RDF and N-Quads.

> Using an AI coding assistant? [llms.txt](llms.txt) contains a compact API reference you can share with the model.

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
use Rasuvaeff\JsonLd\ExpandOptions;
use Rasuvaeff\JsonLd\JsonLd;

$options = new ExpandOptions(base: 'https://example.com/page', loader: $loader);

$expanded = JsonLd::expand($document, $options);   // list of node/value objects
$flat     = JsonLd::flatten($document, $options);  // nodes keyed by @id, blank nodes relabeled _:b0, _:b1, ...
$dataset  = JsonLd::toRdf($document, $options);    // Dataset of quads
$nquads   = JsonLd::serializeNQuads($dataset);
$back     = JsonLd::fromRdf(JsonLd::parseNQuads($nquads));
```

`$document` is a decoded array or an inline JSON string.

| Class | Purpose |
|---|---|
| `JsonLd` | Facade: `expand`, `flatten`, `document`, `toRdf`, `fromRdf`, `serializeNQuads`, `parseNQuads` |
| `ExpandOptions` | `base`, `documentUrl`, `expandContext`, `loader` |
| `Expander`, `Flattener` | The algorithms behind the facade |
| `Iri` | RFC 3986 parsing and relative reference resolution |
| `Loader\ContextLoaderInterface` | How remote contexts are fetched; `InlineContextLoader` (default, no I/O), `PsrContextLoader` (PSR-18, URL policy, size limit) |
| `Graph\Document`, `Graph`, `Node` | Node API over the expanded form |
| `Rdf\Dataset`, `Quad`, `Iri`, `BlankNode`, `Literal`, `NQuads` | RDF model; `Dataset::equals()` compares up to blank node renaming |
| `Exception\*` | Every message is the W3C error code (`invalid @id value`, ...) |

Supported: expansion, flattening, RDF conversion (`toRdf`/`fromRdf`, native types,
`rdfDirection` `i18n-datatype` and `compound-literal`, JSON literals with JCS),
scoped contexts, `@protected`, `@import`, `@propagate`, `@nest`, `@included`,
all container types. Not implemented: compaction, framing, the 1.0 processing mode.

## Security

- **No implicit I/O.** The default loader never touches the network or the filesystem:
  a remote `@context` fails with `loading remote context failed` unless you inject a
  `ContextLoaderInterface`.
- `PsrContextLoader` takes an optional URL policy (`Closure(string): bool`) and a body size
  limit (default 1 MiB). Treat documents as untrusted: allow-list hosts, refuse private
  ranges, set timeouts on the PSR-18 client.
- Expansion depth is capped at 512 levels and remote context chains at 64. Cap the raw
  document size yourself before decoding it.

## Examples

Runnable scripts (no network) in [examples/](examples/):

| Script | Shows |
|---|---|
| [`expand_article.php`](examples/expand_article.php) | expanding a schema.org Article/VideoObject `@graph` |
| [`context_features.php`](examples/context_features.php) | prefixes, `@vocab`, keyword aliases, `@container` |
| [`loaders.php`](examples/loaders.php) | the default loader refuses URLs; injecting a loader; unknown context error |
| [`flatten_node_api.php`](examples/flatten_node_api.php) | `flatten` and the node API on the same document |
| [`rdf.php`](examples/rdf.php) | `toRdf`, N-Quads, `fromRdf` round trip |
| [`html_script.php`](examples/html_script.php) | extracting `application/ld+json` blocks from a page |

Examples are expected to execute without fatal errors and stay aligned with the
documented public API.

## AI agent skill

The package ships an agent skill: [`resources/skills/rasuvaeff-json-ld/SKILL.md`](resources/skills/rasuvaeff-json-ld/SKILL.md)
(distributed through `extra.skills`).

## Conformance

The W3C JSON-LD API test suite (expand, flatten, toRdf, fromRdf) is vendored under
`tests/fixtures/w3c` and run in full, except tests of the 1.0 processing mode and one
compaction-dependent case. The test data belongs to the W3C and is used under the
[W3C Test Suite License and 3-clause BSD License](tests/fixtures/w3c/LICENSE.md)
(Copyright W3C and the JSON-LD Working Group).

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
