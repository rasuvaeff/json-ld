# rasuvaeff/json-ld

[![Latest Stable Version](https://poser.pugx.org/rasuvaeff/json-ld/v)](https://packagist.org/packages/rasuvaeff/json-ld)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/json-ld/downloads)](https://packagist.org/packages/rasuvaeff/json-ld)
[![Build](https://github.com/rasuvaeff/json-ld/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/json-ld/actions/workflows/build.yml)
[![Static analysis](https://github.com/rasuvaeff/json-ld/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/json-ld/actions/workflows/static-analysis.yml)
[![Psalm level](https://img.shields.io/badge/psalm-level_1-blue.svg)](https://github.com/rasuvaeff/json-ld/actions/workflows/static-analysis.yml)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/json-ld/php)](https://packagist.org/packages/rasuvaeff/json-ld)
[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE.md)

Процессор JSON-LD 1.1 для PHP: expand, flatten, Node API, RDF и N-Quads.

> Работаете с AI-ассистентом? [llms.txt](llms.txt) — компактный API-справочник,
> который можно передать модели.

## Требования

- PHP 8.3+
- `ext-json`, `ext-mbstring`, `ext-dom`
- `psr/http-client` ^1.0, `psr/http-factory` ^1.0, `psr/http-message` ^1.0 || ^2.0

## Установка

```bash
composer require rasuvaeff/json-ld
```

## Использование

<!-- Добавьте примеры использования. Для каждого публичного класса:
- Как создать/сконструировать
- Ключевые методы с примерами
- Таблицу публичного API
-->

## Безопасность

<!-- Опишите, что безопасно, а что требует внимания.
Для этого пакета:
- Загрузка удалённых контекстов происходит только через явно внедрённую
  реализацию ContextLoaderInterface; загрузчик по умолчанию не обращается
  ни к сети, ни к файловой системе
-->

## Примеры

См. [examples/](examples/) — исполняемые скрипты.
Примеры обязаны выполняться без фатальных ошибок и соответствовать
документированному публичному API.

## Разработка

PHP/Composer на хосте нет — всё через Docker-образ `composer:2`:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer install
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
docker run --rm -v "$PWD":/app -w /app composer:2 composer release-check
```

Или через Make:

```bash
make install
make build
make cs-fix
make test
make test-coverage
make mutation
make release-check
```

`make test-coverage` и `make mutation` ставят `pcov` внутри контейнера
`composer:2` — в базовом образе нет coverage-драйвера.

## Лицензия

[BSD-3-Clause](LICENSE.md)
