# rasuvaeff/json-ld

[![Latest Stable Version](https://poser.pugx.org/rasuvaeff/json-ld/v)](https://packagist.org/packages/rasuvaeff/json-ld)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/json-ld/downloads)](https://packagist.org/packages/rasuvaeff/json-ld)
[![Build](https://github.com/rasuvaeff/json-ld/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/json-ld/actions/workflows/build.yml)
[![Static analysis](https://github.com/rasuvaeff/json-ld/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/json-ld/actions/workflows/static-analysis.yml)
[![Psalm level](https://img.shields.io/badge/psalm-level_1-blue.svg)](https://github.com/rasuvaeff/json-ld/actions/workflows/static-analysis.yml)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/json-ld/php)](https://packagist.org/packages/rasuvaeff/json-ld)
[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE.md)

Процессор JSON-LD 1.1 для PHP: expand, flatten, Node API, конвертация в RDF, N-Quads
и каноникализация RDF-датасетов W3C RDFC-1.0.

Пакет **читает** JSON-LD: превращает документы в развёрнутую/плоскую форму, граф узлов
или RDF. Это не генератор schema.org-разметки — чтобы выводить
`<script type="application/ld+json">` на своих страницах, используйте
[rasuvaeff/yii3-seo](https://github.com/rasuvaeff/yii3-seo).

> Работаете с AI-ассистентом? [llms.txt](llms.txt) — компактный API-справочник,
> который можно передать модели.

## Объём

| Поддержано (контракт 1.0) | Не реализовано |
|---|---|
| Expand, flatten, Node API (`Graph\Document`) | Compaction |
| `toRdf` / `fromRdf`, N-Quads | Framing |
| Каноникализация RDFC-1.0 (`Rdf\Canonicalizer`) | Режим обработки JSON-LD 1.0 |

Compaction и framing в 1.0 не входят.

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
use Rasuvaeff\JsonLd\JsonLd;
use Rasuvaeff\JsonLd\Loader\MapContextLoader;
use Rasuvaeff\JsonLd\ProcessingOptions;

$options = new ProcessingOptions(
    base: 'https://example.com/page',
    loader: new MapContextLoader(['https://schema.org/' => ['@context' => ['@vocab' => 'https://schema.org/']]]),
);

$expanded = JsonLd::expand($document, $options);   // список node/value-объектов
$flat     = JsonLd::flatten($document, $options);  // узлы по @id, blank-узлы _:b0, _:b1, ...
$graph    = JsonLd::document($document, $options)->defaultGraph();
$dataset  = JsonLd::toRdf($document, $options);    // Dataset из квадов
$nquads   = JsonLd::serializeNQuads($dataset);
$back     = JsonLd::fromRdf(JsonLd::parseNQuads($nquads));
$hash     = $dataset->canonicalHash();             // SHA-256 канонических N-Quads по RDFC-1.0
```

`$document` — декодированный массив или JSON-строка: inline JSON (начинается с `{` или `[`)
декодируется, любая другая строка — URL документа, который получает загрузчик.

### Классы

| Класс | Назначение |
|---|---|
| `JsonLd` | Фасад: `expand`, `flatten`, `document`, `toRdf`, `fromRdf`, `serializeNQuads`, `parseNQuads` |
| `ProcessingOptions` | `base`, `documentUrl`, `expandContext`, `loader`, `maxDepth` (по умолчанию 512), `maxBytes` (по умолчанию без лимита) |
| `RdfDirection` | `rdfDirection` для `toRdf`/`fromRdf`: `I18nDatatype`, `CompoundLiteral` |
| `Expander`, `Flattener` | Алгоритмы за фасадом |
| `Iri` | Разбор по RFC 3986 и разрешение относительных ссылок |
| `Loader\ContextLoaderInterface`, `LoadedContext` | Как загружаются удалённые контексты и документы |
| `Loader\InlineContextLoader` | По умолчанию: разбирает inline JSON, отвергает любой URL, без I/O |
| `Loader\MapContextLoader` | Фиксированная карта URL → декодированный документ, точное совпадение, без I/O |
| `Loader\PsrContextLoader` | PSR-18 клиент, политика URL, лимит размера тела |
| `Graph\Document`, `Graph`, `Node`, `Value` | Node API (ниже) |
| `Rdf\Dataset`, `Quad`, `Iri`, `BlankNode`, `Literal`, `NQuads` | RDF-модель и N-Quads |
| `Rdf\Vocabulary` | IRI терминов RDF/XSD (`RDF_TYPE`, `XSD_STRING`, `RDF_LANGSTRING`, ...) |
| `Rdf\Canonicalizer`, `CanonicalForm`, `HashAlgorithm` | RDFC-1.0 (ниже) |
| `Exception\JsonLdException`, `ErrorCode` | Ошибки (ниже) |

### Node API

`JsonLd::document()` строит графы из node map — той же структуры, что и flatten, — поэтому
каждый node-объект документа, включая вложенные, становится узлом своего графа, а
вложенные объекты превращаются в ссылки:

```php
$article = $graph->node('https://example.com/articles/1');
$author  = $article?->reference('https://schema.org/author');   // вложенный, без @id: всё равно достижим
$author?->id();                                                 // "_:b0"
$author?->value('https://schema.org/name');                     // "Alice"

foreach ($node->valueObjects('https://schema.org/name') as $name) {
    $name->value; $name->language; $name->type; $name->direction;
}
```

- Узлы без `@id` получают метки blank-узлов `_:b0`, `_:b1`, … в порядке обхода
  алгоритма; метки blank-узлов из входа переименовываются так же. Для одного и того же
  входа метки стабильны, но это не метки входа. IRI сохраняются.
- Узлы идут в порядке документа. Голая ссылка (`{"@id": "…"}`, которую ничто не описывает)
  — узел без свойств, поэтому `reference()` её разрешает.
- `Node`: `id()`, `types()`, `hasType()`, `properties()`, `values()` (скаляры),
  `value()`, `valueObjects()` (`Graph\Value` с языком, типом данных и направлением),
  `references()`, `reference()`, `toArray()` (плоский node-объект).
- `Graph`: `nodes()`, `node($id)`, `byType($iri)`; `Document`: `defaultGraph()`,
  `graph($name)`, `graphs()`, `Document::fromExpanded($expanded)`.

### Каноникализация RDF-датасетов

`Rdf\Canonicalizer` реализует [RDFC-1.0](https://www.w3.org/TR/rdf-canon/): у двух
датасетов одинаковые канонические N-Quads ровно тогда, когда они изоморфны.

```php
use Rasuvaeff\JsonLd\Rdf\Canonicalizer;
use Rasuvaeff\JsonLd\Rdf\HashAlgorithm;

$dataset->canonicalNQuads();   // отсортированные канонические N-Quads, blank-узлы _:c14n0, _:c14n1, ...
$dataset->canonicalHash();     // hex SHA-256 от canonicalNQuads()
$dataset->equals($other);      // изоморфизм: одинаковые канонические N-Quads

$form = (new Canonicalizer(hashAlgorithm: HashAlgorithm::Sha384, workFactor: 4))->canonicalize($dataset);
$form->nquads;                 // канонические N-Quads
$form->issuedIdentifiers;      // ['_:e0' => '_:c14n0', ...] метка входа => каноническая метка
$form->hash();                 // дайджест тем же алгоритмом
```

**Poison-графы.** Некоторым датасетам (клики blank-узлов) нужна факториальная работа.
Если у `n` blank-узлов совпадает first-degree хеш, Hash N-Degree Quads может выполниться
не больше `workFactor × n` раз на blank-узел (по умолчанию `workFactor` 4), что ограничивает
прогон `workFactor × n²` вызовами; дальше бросается
`Exception\CanonicalizationLimitException` (код `canonicalization limit exceeded`).
Легитимным симметричным формам — циклу, списку одинаковых значений — нужно около `n`
вызовов на blank-узел. `canonicalNQuads()`, `canonicalHash()` и `equals()` тоже могут его
бросить.

### Ошибки

Все исключения наследуют `Exception\JsonLdException`; `errorCode()` возвращает
`Exception\ErrorCode` — это контракт, по которому ветвиться. Сообщение — для людей: оно
равно коду ошибки, если процессору нечего добавить (отказ загрузчика, синтаксическая
ошибка JSON).

| Исключение | Коды ошибок |
|---|---|
| `InvalidContextException` | коды W3C для контекстов: `invalid term definition`, `invalid remote context`, … |
| `InvalidInputException` | коды W3C для документа (`invalid @id value`, …); `document nested too deeply`, `document too large` |
| `ContextRecursionException` | `context overflow`, `recursive context inclusion` |
| `DocumentLoadingException` | `loading remote context failed`, `loading document failed` |
| `InvalidJsonException` | `loading document failed` (inline JSON, который не JSON или не объект/массив) |
| `InvalidNQuadsException` | `invalid N-Quads` |
| `CanonicalizationLimitException` | `canonicalization limit exceeded` |

Четыре кода — собственные коды процессора: `document nested too deeply`, `document too large`,
`invalid N-Quads`, `canonicalization limit exceeded`. `recursive context inclusion` — код
JSON-LD 1.0, который всё ещё ожидает тестовый набор W3C; все остальные — коды ошибок
W3C JSON-LD 1.1 API.

**Что бы ни бросил загрузчик** — `DocumentLoadingException`, исключение PSR-18, любой
`Throwable`, — вызывающий получает `DocumentLoadingException`: `loading remote context
failed` для контекста, `loading document failed` для самого документа, исключение
загрузчика — в `getPrevious()`.

## Безопасность

- **Никакого неявного I/O.** Загрузчик по умолчанию не обращается ни к сети, ни к файловой
  системе: удалённый `@context` завершается `loading remote context failed`, пока вы не
  передали `ContextLoaderInterface`. Для известных контекстов лучше `MapContextLoader`.
- `PsrContextLoader` принимает необязательную политику URL (`Closure(string): bool`) и лимит
  размера тела (по умолчанию 1 МиБ). Документы недоверенные: белый список хостов, запрет
  приватных диапазонов, таймауты на PSR-18 клиенте.
- **Недоверенные JSON-строки.** `ProcessingOptions(maxBytes: …)` ограничивает длину inline
  JSON-строки до декодирования (`document too large`); `maxDepth` (по умолчанию 512)
  ограничивает вложенность и при декодировании строки, и при expand
  (`document nested too deeply`). Цепочка удалённых контекстов обрывается на 64
  (`context overflow`).
- **RDFC-1.0** ограничен work factor (см. выше).

## Примеры

Исполняемые скрипты без сети — в [examples/](examples/):

| Скрипт | Что показывает |
|---|---|
| [`expand_article.php`](examples/expand_article.php) | expand schema.org Article/VideoObject с `@graph` |
| [`context_features.php`](examples/context_features.php) | префиксы, `@vocab`, алиасы ключевых слов, `@container` |
| [`loaders.php`](examples/loaders.php) | загрузчик по умолчанию отвергает URL; `MapContextLoader`; коды ошибок; лимиты входа |
| [`flatten_node_api.php`](examples/flatten_node_api.php) | `flatten`, Node API, вложенные узлы, значения с языком |
| [`rdf.php`](examples/rdf.php) | `toRdf`, N-Quads, обратно через `fromRdf` |
| [`canonicalize.php`](examples/canonicalize.php) | RDFC-1.0: канонические N-Quads, хеши, выданные метки, лимит poison-графов |
| [`html_script.php`](examples/html_script.php) | извлечение блоков `application/ld+json` со страницы |

Примеры обязаны выполняться без фатальных ошибок и соответствовать
документированному публичному API.

## Навык для AI-агентов

Пакет поставляет навык: [`resources/skills/rasuvaeff-json-ld/SKILL.md`](resources/skills/rasuvaeff-json-ld/SKILL.md)
(раздаётся через `extra.skills`).

## Соответствие спецификации

- Тестовый набор W3C JSON-LD API (expand, flatten, toRdf, fromRdf) лежит в
  `tests/fixtures/w3c` и проходит целиком, кроме тестов режима обработки 1.0 и одного
  случая, которому нужна compaction. Тестовые данные принадлежат W3C и используются по
  [лицензии W3C Test Suite и 3-clause BSD](tests/fixtures/w3c/LICENSE.md)
  (Copyright W3C and the JSON-LD Working Group).
- Тестовый набор W3C RDF Dataset Canonicalization лежит в `tests/fixtures/rdf-canon` и
  проходит целиком: 64 evaluation-теста (SHA-256 и SHA-384), 21 тест карты выданных меток
  и негативный тест poison-графа — 86 из 86, без пропусков
  ([лицензия](tests/fixtures/rdf-canon/LICENSE.md), Copyright W3C).

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
