# Examples

| Script | Shows | Needs server? |
|---|---|---|
| `expand_article.php` | `JsonLd::expand()` of a schema.org Article/VideoObject `@graph` | no |
| `context_features.php` | prefixes, `@vocab`, keyword aliases, `@container` | no |
| `loaders.php` | the default loader refuses URLs; `MapContextLoader`; error codes; `maxDepth`/`maxBytes` | no |
| `flatten_node_api.php` | `flatten()`, the node API, embedded nodes without `@id`, language-tagged values | no |
| `rdf.php` | `toRdf()`, N-Quads serialization and parsing, `fromRdf()`, dataset equality | no |
| `canonicalize.php` | RDFC-1.0: canonical N-Quads, hashes, issued labels, the poison-graph limit | no |
| `html_script.php` | extracting `application/ld+json` blocks from a page (needs `ext-dom`) | no |

`support.php` and `article.php` are shared helpers (a `MapContextLoader` with the
schema.org context, the sample document).

Run (no PHP on the host — Docker, as everywhere in this monorepo):

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 php examples/flatten_node_api.php
```

A script needs `vendor/` — run `composer install` first (same container).
