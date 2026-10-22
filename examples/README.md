# Examples

| Script | Shows | Needs server? |
|---|---|---|
| <!-- script --> | <!-- description --> | <!-- yes/no --> |

Run (no PHP on the host — Docker, as everywhere in this monorepo):

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 php examples/<script>.php
```

A script needs `vendor/` — run `composer install` first (same container).
If a script needs external services, pass them via environment variables
(`-e VAR=value`) and note it in the table above.
