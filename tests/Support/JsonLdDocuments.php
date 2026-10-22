<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Tests\Support;

use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Gen;

/**
 * Generators of compact JSON-LD documents built from a small vocabulary:
 * simple term definitions, `@vocab`, `@language`, scalar values, typed and
 * language-tagged value objects, and node references. Documents produced
 * here are valid by construction, which keeps the algebraic laws testable
 * without a validator.
 */
final readonly class JsonLdDocuments
{
    /**
     * A compact document: optional context plus 1-3 node objects. Nodes are
     * generated from the context's own terms, so every value matches its
     * term coercion and the document is valid by construction.
     */
    public static function document(): ArbitraryInterface
    {
        return Gen::flatMap(
            self::context(),
            static fn(array $context): ArbitraryInterface => Gen::map(
                Gen::record([
                    'context' => Gen::constant($context),
                    'nodes' => Gen::nonEmptyArrayOf(self::node($context), maxSize: 3),
                ]),
                static fn(array $doc): array => self::normalize($doc),
            ),
        );
    }

    /**
     * A context object with simple term definitions and optional defaults.
     */
    public static function context(): ArbitraryInterface
    {
        $termName = Gen::stringFrom('abcdefghij', minLength: 2, maxLength: 6);

        $simple = Gen::map(
            Gen::stringFrom('abcdefghij', minLength: 2, maxLength: 6),
            static fn(string $t): array => ['@id' => 'http://example.com/vocab/' . $t],
        );

        $typed = Gen::map(
            Gen::record([
                'iri' => Gen::stringFrom('abcdefghij', minLength: 2, maxLength: 6),
                'type' => Gen::elements(['@id', 'http://example.com/typed']),
            ]),
            static fn(array $t): array => ['@id' => 'http://example.com/vocab/' . $t['iri'], '@type' => $t['type']],
        );

        $terms = Gen::dictOf(
            $termName,
            Gen::frequency([
                [3, $simple],
                [1, $typed],
            ]),
        );

        return Gen::map(
            Gen::record([
                'vocab' => Gen::nullable(Gen::constant('http://example.com/vocab/')),
                'language' => Gen::nullable(Gen::elements(['en', 'de', 'ru'])),
                'terms' => $terms,
            ]),
            static function (array $c): array {
                $context = [];

                if ($c['vocab'] !== null) {
                    $context['@vocab'] = $c['vocab'];
                }

                if ($c['language'] !== null) {
                    $context['@language'] = $c['language'];
                }

                foreach ($c['terms'] as $term => $definition) {
                    if (in_array($term, ['vocab', 'language'], strict: true)) {
                        continue;
                    }

                    $context[$term] = $definition;
                }

                return $context;
            },
        );
    }

    /**
     * A node object built on the given context: optional @id/@type plus
     * 0-4 properties taken from the context terms, each value compatible
     * with the term's coercion.
     *
     * @param array<string, mixed> $context
     */
    public static function node(array $context): ArbitraryInterface
    {
        $terms = array_values(array_filter(
            array_keys($context),
            static fn(string $key): bool => !str_starts_with($key, '@') && !in_array($key, ['vocab', 'language'], strict: true),
        ));

        $id = Gen::frequency([
            [2, Gen::map(
                Gen::stringFrom('abcdefghij0123456789', minLength: 1, maxLength: 8),
                static fn(string $s): string => 'http://example.com/resource/' . $s,
            )],
            [1, Gen::map(
                Gen::stringFrom('abcdefghij', minLength: 1, maxLength: 6),
                static fn(string $s): string => '_:' . $s,
            )],
        ]);

        return Gen::map(
            Gen::record([
                'id' => $id,
                'type' => Gen::nullable(Gen::elements(['http://example.com/Thing', 'http://example.com/Event'])),
                'props' => Gen::dictOf(
                    Gen::elements($terms === [] ? ['term'] : $terms),
                    self::propertyValue($context),
                ),
            ]),
            static function (array $n): array {
                $node = [];
                if ($n['id'] !== null) {
                    $node['@id'] = $n['id'];
                }
                if ($n['type'] !== null) {
                    $node['@type'] = $n['type'];
                }
                foreach ($n['props'] as $key => $value) {
                    if ($key === 'id' || $key === 'type') {
                        continue;
                    }

                    $node[$key] = $value;
                }

                return $node;
            },
        );
    }

    /**
     * One property value compatible with the term's coercion: strings for
     * `@id`-coerced terms, scalars or language-tagged value objects
     * otherwise, plus plain node references.
     *
     * @param array<string, mixed> $context
     */
    public static function propertyValue(array $context): ArbitraryInterface
    {
        $coercions = array_map(
            static fn(mixed $definition): ?string => is_array($definition) && isset($definition['@type'])
                ? $definition['@type']
                : null,
            $context,
        );

        return Gen::frequency([
            [3, self::scalar($coercions)],
            [1, Gen::map(
                Gen::record([
                    'text' => Gen::stringFrom('abcdefghij', minLength: 1, maxLength: 8),
                    'lang' => Gen::elements(['en', 'de', 'ru']),
                ]),
                static fn(array $v): array => ['@value' => $v['text'], '@language' => $v['lang']],
            )],
            [1, Gen::map(
                Gen::stringFrom('abcdefghij', minLength: 1, maxLength: 8),
                static fn(string $s): array => ['@id' => 'http://example.com/resource/' . $s],
            )],
        ]);
    }

    /**
     * A scalar whose type is valid for every coercion in the context:
     * `@id`/`@vocab`-coerced terms only accept strings.
     *
     * @param array<string|null> $coercions
     */
    private static function scalar(array $coercions): ArbitraryInterface
    {
        $hasIriCoercion = in_array('@id', $coercions, strict: true) || in_array('@vocab', $coercions, strict: true);

        if ($hasIriCoercion) {
            return Gen::stringFrom('value0123 ', minLength: 0, maxLength: 12);
        }

        return Gen::frequency([
            [2, Gen::stringFrom('value0123 ', minLength: 0, maxLength: 12)],
            [1, Gen::intBetween(-500, 500)],
            [1, Gen::bool()],
        ]);
    }

    /**
     * Reassembles the generator record into a plain JSON-LD document.
     *
     * @param array<string, mixed> $doc
     *
     * @return array<string, mixed>|list<mixed>
     */
    private static function normalize(array $doc): array
    {
        $nodes = $doc['nodes'];

        if ($doc['context'] === null) {
            return $nodes;
        }

        $first = $nodes[0] ?? [];
        $rest = array_slice($nodes, 1);
        $withContext = ['@context' => $doc['context'], ...$first];

        return [$withContext, ...$rest];
    }
}
