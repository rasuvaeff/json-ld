# Examples

| Script | Shows | Needs server? |
|---|---|---|
| `expand_flatten.php` | `JsonLd::expand()`, `flatten()`, the base IRI option | no |
| `rdf.php` | `toRdf()`, N-Quads serialization and parsing, `fromRdf()`, dataset equality | no |
| `node_api.php` | the node API over an expanded document | no |
| `custom_loader.php` | a `ContextLoaderInterface` that resolves a remote context without network | no |

Run (no PHP on the host — Docker, as everywhere in this monorepo):

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 php examples/expand_flatten.php
```

A script needs `vendor/` — run `composer install` first (same container).
