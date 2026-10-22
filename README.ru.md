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

- PHP 8.3–8.5 (8.6 проверяется в CI как экспериментальная)
- `ext-json`, `ext-mbstring`
- `psr/http-client` ^1.0, `psr/http-factory` ^1.0, `psr/http-message` ^1.0 || ^2.0

## Установка

```bash
composer require rasuvaeff/json-ld
```

## Использование

```php
use Rasuvaeff\JsonLd\ExpandOptions;
use Rasuvaeff\JsonLd\JsonLd;

$options = new ExpandOptions(base: 'https://example.com/page', loader: $loader);

$expanded = JsonLd::expand($document, $options);   // список node/value-объектов
$flat     = JsonLd::flatten($document, $options);  // узлы по @id, blank-узлы _:b0, _:b1, ...
$dataset  = JsonLd::toRdf($document, $options);    // Dataset из квадов
$nquads   = JsonLd::serializeNQuads($dataset);
$back     = JsonLd::fromRdf(JsonLd::parseNQuads($nquads));
```

`$document` — декодированный массив или inline JSON-строка.

| Класс | Назначение |
|---|---|
| `JsonLd` | Фасад: `expand`, `flatten`, `document`, `toRdf`, `fromRdf`, `serializeNQuads`, `parseNQuads` |
| `ExpandOptions` | `base`, `documentUrl`, `expandContext`, `loader` |
| `Expander`, `Flattener` | Алгоритмы за фасадом |
| `Iri` | Разбор по RFC 3986 и разрешение относительных ссылок |
| `Loader\ContextLoaderInterface` | Как загружаются удалённые контексты; `InlineContextLoader` (по умолчанию, без I/O), `PsrContextLoader` (PSR-18, политика URL, лимит размера) |
| `Graph\Document`, `Graph`, `Node` | Node API поверх развёрнутой формы |
| `Rdf\Dataset`, `Quad`, `Iri`, `BlankNode`, `Literal`, `NQuads` | RDF-модель; `Dataset::equals()` сравнивает с точностью до переименования blank-узлов |
| `Exception\*` | Сообщение каждого исключения — код ошибки W3C (`invalid @id value`, ...) |

Поддержано: expand, flatten, конвертация в RDF (`toRdf`/`fromRdf`, native types,
`rdfDirection` `i18n-datatype` и `compound-literal`, JSON-литералы через JCS),
scoped-контексты, `@protected`, `@import`, `@propagate`, `@nest`, `@included`, все
типы контейнеров. Не реализовано: compaction, framing, режим обработки 1.0.

## Безопасность

- **Никакого неявного I/O.** Загрузчик по умолчанию не обращается ни к сети, ни к файловой
  системе: удалённый `@context` завершается `loading remote context failed`, пока вы не
  передали `ContextLoaderInterface`.
- `PsrContextLoader` принимает необязательную политику URL (`Closure(string): bool`) и лимит
  размера тела (по умолчанию 1 МиБ). Документы недоверенные: белый список хостов, запрет
  приватных диапазонов, таймауты на PSR-18 клиенте.
- Глубина разбора ограничена 512 уровнями, цепочка удалённых контекстов — 64. Размер
  исходного документа ограничивайте сами до декодирования.

## Примеры

Исполняемые скрипты без сети — в [examples/](examples/):

| Скрипт | Что показывает |
|---|---|
| [`expand_article.php`](examples/expand_article.php) | expand schema.org Article/VideoObject с `@graph` |
| [`context_features.php`](examples/context_features.php) | префиксы, `@vocab`, алиасы ключевых слов, `@container` |
| [`loaders.php`](examples/loaders.php) | загрузчик по умолчанию отвергает URL; подмена загрузчика; ошибка неизвестного контекста |
| [`flatten_node_api.php`](examples/flatten_node_api.php) | `flatten` и Node API на одном документе |
| [`rdf.php`](examples/rdf.php) | `toRdf`, N-Quads, обратно через `fromRdf` |
| [`html_script.php`](examples/html_script.php) | извлечение блоков `application/ld+json` со страницы |

Примеры обязаны выполняться без фатальных ошибок и соответствовать
документированному публичному API.

## Навык для AI-агентов

Пакет поставляет навык: [`resources/skills/rasuvaeff-json-ld/SKILL.md`](resources/skills/rasuvaeff-json-ld/SKILL.md)
(раздаётся через `extra.skills`).

## Соответствие спецификации

Тестовый набор W3C JSON-LD API (expand, flatten, toRdf, fromRdf) лежит в
`tests/fixtures/w3c` и проходит целиком, кроме тестов режима обработки 1.0 и одного
случая, которому нужна compaction. Тестовые данные принадлежат W3C и используются по
[лицензии W3C Test Suite и 3-clause BSD](tests/fixtures/w3c/LICENSE.md)
(Copyright W3C and the JSON-LD Working Group).

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
