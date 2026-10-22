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

- PHP 8.3+
- `ext-json`, `ext-mbstring`, `ext-dom`
- `psr/http-client` ^1.0, `psr/http-factory` ^1.0, `psr/http-message` ^1.0 || ^2.0

## Installation

```bash
composer require rasuvaeff/json-ld
```

## Usage

<!-- Add usage examples here. For each public class, show:
- How to create/construct
- Key methods with examples
- Table of public API
-->

## Security

<!-- Describe what is safe and what requires caution.
For this package:
- Remote context loading happens only through an explicitly injected
  ContextLoaderInterface implementation; the default loader never touches
  network or filesystem
-->

## Examples

See [examples/](examples/) for runnable scripts.
Examples are expected to execute without fatal errors and stay aligned with the
documented public API.

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
