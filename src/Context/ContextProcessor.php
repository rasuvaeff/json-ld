<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Context;

use Rasuvaeff\JsonLd\Exception\ContextRecursionException;
use Rasuvaeff\JsonLd\Exception\DocumentLoadingException;
use Rasuvaeff\JsonLd\Exception\InvalidContextException;
use Rasuvaeff\JsonLd\Iri;
use Rasuvaeff\JsonLd\Keywords;

/**
 * Context processing, JSON-LD 1.1 API section 5.1.1, including JSON-LD 1.1
 * features: scoped contexts, `@protected`, `@import`, `@propagate`,
 * `@prefix`, `@version`, `@base`, `@vocab`, `@language`, `@direction`.
 *
 * Every thrown exception message equals the corresponding JSON-LD error
 * code from the W3C test suite (for example `invalid @version value`).
 *
 * @api
 */
final class ContextProcessor
{
    private const int MAX_DEPTH = 32;

    /** Characters that let a simple term definition act as a compact IRI prefix. */
    private const string GEN_DELIMS = ':/?#';

    /**
     * Container mappings a definition may combine, as sorted keyword lists.
     */
    private const array CONTAINERS = [
        '@list',
        '@set',
        '@index',
        '@id',
        '@type',
        '@language',
        '@graph',
        '@graph,@id',
        '@graph,@set',
        '@graph,@index',
        '@graph,@type',
        '@set,@id',
        '@set,@index',
        '@set,@type',
        '@graph,@id,@set',
        '@graph,@index,@set',
        '@graph,@set,@type',
    ];

    /**
     * Processes a local context into a new active context.
     *
     * @param ActiveContext $activeContext the context to process the local context against
     * @param string|array<mixed>|null $localContext a context reference, a list of contexts, or a context object
     * @param ProcessOptions $options processing options; `propagate` controls whether the
     *        resulting context chains to the current one through `previousContext`
     *
     * @throws InvalidContextException with a W3C error code as the message
     * @throws ContextRecursionException when remote contexts include each other in a cycle
     */
    public function process(ActiveContext $activeContext, string|array|null $localContext, ProcessOptions $options): ActiveContext
    {
        if ($options->depth > self::MAX_DEPTH) {
            throw new ContextRecursionException('context overflow');
        }

        if (is_string($localContext)) {
            return $this->processReference($activeContext, $localContext, $options->deeper());
        }

        if ($localContext === null) {
            $localContext = [];
        }

        if (!is_string($localContext) && !is_array($localContext)) {
            throw new InvalidContextException('invalid local context');
        }

        if (array_is_list($localContext)) {
            $context = $activeContext;

            foreach ($localContext as $item) {
                if ($item === null) {
                    if ($options->scoped) {
                        throw new InvalidContextException('invalid context nullification');
                    }

                    // null resets the accumulated context.
                    $context = new ActiveContext(base: $options->base);
                    $options = $options->withBase($context->base);

                    continue;
                }

                if (!is_string($item) && !is_array($item)) {
                    throw new InvalidContextException('invalid local context');
                }

                $context = $this->process($context, $item, $options);
            }

            return $context;
        }

        return $this->processObject($activeContext, $localContext, $options);
    }

    /**
     * Loads and processes a context referenced by a string: inline JSON or a
     * location resolved by the injected loader.
     */
    private function processReference(ActiveContext $activeContext, string $reference, ProcessOptions $options): ActiveContext
    {
        $trimmed = ltrim($reference);

        if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
            $loaded = $this->load($reference, $options);

            return $this->process($activeContext, $this->extractContext($loaded->document), $options);
        }

        $resolved = $options->base !== null
            ? (string) $options->base->resolve(Iri::of($reference))
            : $reference;

        if (in_array($resolved, $options->remoteContexts, true)) {
            throw new ContextRecursionException('recursive context inclusion');
        }

        $options = $options->withRemoteContext($resolved);
        $loaded = $this->load($resolved, $options);

        $documentUrl = $loaded->documentUrl !== '' ? $loaded->documentUrl : $resolved;
        $options = $options->withBase(Iri::of($documentUrl));

        if ($loaded->contextUrl !== null) {
            $activeContext = $this->process($activeContext, $loaded->contextUrl, $options);
        }

        return $this->process($activeContext, $this->extractContext($loaded->document), $options);
    }

    /**
     * @param array<string|int, mixed> $document
     *
     * @return array<string, mixed>|list<mixed>|string
     */
    private function extractContext(array $document): array|string
    {
        if (array_key_exists('@context', $document)) {
            $context = $document['@context'];

            if (is_string($context) || is_array($context)) {
                return $context;
            }

            throw new InvalidContextException('invalid remote context');
        }

        return $document;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function processObject(ActiveContext $activeContext, array $context, ProcessOptions $options): ActiveContext
    {
        $builder = ContextBuilder::from($activeContext);

        if (array_key_exists(Keywords::VERSION, $context)) {
            $version = $context[Keywords::VERSION];

            if (!is_int($version) && !is_float($version) || (float) $version !== 1.1) {
                throw new InvalidContextException('invalid @version value');
            }
        }

        if (array_key_exists(Keywords::IMPORT, $context)) {
            $import = $context[Keywords::IMPORT];

            if (!is_string($import)) {
                throw new InvalidContextException('invalid @import value');
            }

            if (in_array($import, $options->remoteContexts, true)) {
                throw new ContextRecursionException('recursive context inclusion');
            }

            $activeContext = $this->process($activeContext, $import, $options->deeper());
            $builder = ContextBuilder::from($activeContext);
            $builder = ContextBuilder::from($activeContext);
        }

        if (array_key_exists(Keywords::BASE, $context)) {
            $base = $context[Keywords::BASE];

            if ($base === null) {
                $builder->setBase($options->base);
            } elseif (is_string($base)) {
                $resolved = IriExpansion::expand(
                    $builder->freeze(),
                    $base,
                    documentRelative: true,
                );

                if ($resolved !== null && (IriExpansion::isAbsolute($resolved) || str_starts_with($resolved, '_:'))) {
                    $builder->setBase(Iri::of($resolved));
                } else {
                    throw new InvalidContextException('invalid base IRI');
                }
            } else {
                throw new InvalidContextException('invalid base IRI');
            }
        }

        if (array_key_exists(Keywords::VOCAB, $context)) {
            $vocab = $context[Keywords::VOCAB];

            if ($vocab === null) {
                $builder->setVocab(null);
            } elseif (is_string($vocab)) {
                $mapping = IriExpansion::expand($builder->freeze(), $vocab, vocab: true, documentRelative: true);

                if ($mapping === null || !IriExpansion::isAbsolute($mapping)) {
                    throw new InvalidContextException('invalid vocab mapping');
                }

                $builder->setVocab($mapping);
            } else {
                throw new InvalidContextException('invalid vocab mapping');
            }
        }

        if (array_key_exists(Keywords::LANGUAGE, $context)) {
            $language = $context[Keywords::LANGUAGE];

            if ($language === null) {
                $builder->setLanguage(null);
            } elseif (is_string($language)) {
                $builder->setLanguage(mb_strtolower($language));
            } else {
                throw new InvalidContextException('invalid default language');
            }
        }

        if (array_key_exists(Keywords::DIRECTION, $context)) {
            $direction = $context[Keywords::DIRECTION];

            if ($direction === null || $direction === 'ltr' || $direction === 'rtl') {
                $builder->setDirection($direction);
            } else {
                throw new InvalidContextException('invalid @direction value');
            }
        }

        if (array_key_exists(Keywords::PROPAGATE, $context) && !is_bool($context[Keywords::PROPAGATE])) {
            throw new InvalidContextException('invalid @propagate value');
        }

        $protected = false;

        if (array_key_exists(Keywords::PROTECTED, $context)) {
            if (!is_bool($context[Keywords::PROTECTED])) {
                throw new InvalidContextException('invalid @protected value');
            }

            $protected = $context[Keywords::PROTECTED];
        }

        /** @var array<string, bool> $defined */
        $defined = [];

        foreach ($context as $term => $value) {
            if (in_array($term, Keywords::CONTEXT_LEVEL, true)) {
                continue;
            }

            if ($term === Keywords::TYPE && is_array($value) && !array_is_list($value)
                && array_key_exists(Keywords::CONTEXT, $value)
            ) {
                $builder->setTerm(
                    $term,
                    new TermDefinition(iriMapping: null, localContext: $value[Keywords::CONTEXT]),
                );

                continue;
            }

            if (Keywords::isKeyword($term)) {
                throw new InvalidContextException('keyword redefinition');
            }

            if ($value === null) {
                if ($options->scoped) {
                    throw new InvalidContextException('invalid context nullification');
                }

                $existing = $builder->term($term);

                if ($existing !== null && $existing->protected && !$options->overrideProtected) {
                    throw new InvalidContextException('protected term redefinition');
                }

                // A nulled term keeps blocking resolution; it does not fall
                // back to @vocab.
                $builder->nullifyTerm($term);

                continue;
            }

            $this->createTermDefinition($builder, $context, $term, $value, $defined, $protected, $options);
        }

        return $builder->freeze($options->propagate ? null : $activeContext);
    }

    /**
     * Creates a single term definition, JSON-LD 1.1 API section 5.1.3.
     *
     * @param array<string, mixed> $localContext
     * @param array<string, bool> $defined
     */
    private function createTermDefinition(
        ContextBuilder $builder,
        array $localContext,
        string $term,
        mixed $value,
        array &$defined,
        bool $protected,
        ProcessOptions $options,
    ): void {
        if ($term === '' || str_starts_with($term, '_:')) {
            throw new InvalidContextException('invalid term definition');
        }

        $existing = $builder->term($term);

        if ($existing !== null && $existing->protected && !$options->overrideProtected) {
            throw new InvalidContextException('protected term redefinition');
        }

        $defined[$term] = false;
        $simple = is_string($value);

        if (!$simple) {
            $allowed = [
                Keywords::ID,
                Keywords::REVERSE,
                Keywords::TYPE,
                '@container',
                Keywords::INDEX,
                Keywords::CONTEXT,
                Keywords::LANGUAGE,
                Keywords::DIRECTION,
                Keywords::PREFIX,
                Keywords::PROTECTED,
                Keywords::NEST,
            ];

            foreach (array_keys($value) as $member) {
                if (str_starts_with((string) $member, '@') && !in_array($member, $allowed, true)) {
                    throw new InvalidContextException('invalid term definition');
                }
            }
        }

        if ($simple) {
            $value = [Keywords::ID => $value];
        }

        if (!is_array($value) || array_is_list($value)) {
            throw new InvalidContextException('invalid term definition');
        }

        $colon = strpos($term, ':');

        $mapping = null;
        $keyword = null;

        if (array_key_exists(Keywords::ID, $value)) {
            $id = $value[Keywords::ID];

            if ($id === null) {
                $mapping = null;
            } elseif (is_string($id)) {
                $aliasKeyword = Keywords::isKeyword($id)
                    ? $id
                    : $builder->keywordFor($id);

                if ($aliasKeyword !== null) {
                    if ($aliasKeyword === Keywords::JSON || $aliasKeyword === Keywords::CONTEXT) {
                        throw new InvalidContextException('invalid keyword alias');
                    }

                    $keyword = $aliasKeyword;
                } else {
                    $mapping = $this->expandTermIri($builder, $id, true, $localContext, $defined, $options, documentRelative: true);

                    if ($mapping !== null && str_starts_with($mapping, '@')) {
                        if ($mapping === Keywords::JSON || $mapping === Keywords::CONTEXT) {
                            throw new InvalidContextException('invalid keyword alias');
                        }

                        $keyword = $mapping;
                        $mapping = null;
                    } elseif ($mapping !== null && !IriExpansion::isAbsolute($mapping) && !str_starts_with($mapping, '_:')) {
                        throw new InvalidContextException('invalid IRI mapping');
                    }
                }
            } else {
                throw new InvalidContextException('invalid IRI mapping');
            }
        } elseif ($colon !== false) {
            // A term that is already an absolute IRI maps to itself — but
            // only when its prefix is not itself a context term: a compact
            // IRI term expands through its prefix.
            $prefix = substr($term, 0, $colon);

            $prefixKnown = array_key_exists($prefix, $localContext) || $builder->isMapped($prefix);

            $mapping = !$prefixKnown && IriExpansion::hasValidScheme($prefix)
                ? $term
                : $this->expandTermIri($builder, $term, true, $localContext, $defined, $options, documentRelative: true, skipSelf: true);

            if ($mapping === null) {
                throw new InvalidContextException('invalid IRI mapping');
            }
        } elseif ($builder->vocab() !== null) {
            $mapping = $builder->vocab() . $term;
        } elseif ($value !== []
            && !isset($value[Keywords::PROTECTED])
            && !isset($value[Keywords::REVERSE])
        ) {
            // A term without a mapping may not carry other members.
            $extras = array_diff(
                array_keys($value),
                [Keywords::PROTECTED],
            );

            if ($extras !== []) {
                throw new InvalidContextException('invalid IRI mapping');
            }
        }

        $reverse = false;

        if (array_key_exists(Keywords::REVERSE, $value)) {
            if (array_key_exists(Keywords::ID, $value)) {
                throw new InvalidContextException('invalid reverse property');
            }

            $reverseValue = $value[Keywords::REVERSE];

            if (!is_string($reverseValue)) {
                throw new InvalidContextException('invalid IRI mapping');
            }

            $reverseMapping = $this->expandTermIri($builder, $reverseValue, true, $localContext, $defined, $options, documentRelative: true);

            if ($reverseMapping === null || !IriExpansion::isAbsolute($reverseMapping)) {
                throw new InvalidContextException('invalid IRI mapping');
            }

            $mapping = $reverseMapping;
            $reverse = true;

            $reverseContainers = array_intersect(
                $this->containerMapping($value),
                [Keywords::SET, Keywords::INDEX],
            );

            if (count($reverseContainers) !== count($this->containerMapping($value))) {
                throw new InvalidContextException('invalid reverse property');
            }
        }

        $type = null;

        if (array_key_exists(Keywords::TYPE, $value)) {
            $typeValue = $value[Keywords::TYPE];

            if (!is_string($typeValue)) {
                throw new InvalidContextException('invalid type mapping');
            }

            if (in_array($typeValue, [Keywords::ID, Keywords::JSON, Keywords::VOCAB], true)) {
                $type = $typeValue;
            } elseif (str_starts_with($typeValue, '_:')) {
                throw new InvalidContextException('invalid type mapping');
            } else {
                $type = $this->expandTermIri($builder, $typeValue, true, $localContext, $defined, $options);

                if ($type === null || !IriExpansion::isAbsolute($type)) {
                    throw new InvalidContextException('invalid type mapping');
                }
            }
        }

        $container = $this->containerMapping($value);

        $index = null;

        if (array_key_exists(Keywords::INDEX, $value)) {
            if (!is_string($value[Keywords::INDEX]) || !in_array(Keywords::INDEX, $container, true)) {
                throw new InvalidContextException('invalid index mapping');
            }

            $index = $value[Keywords::INDEX];
        }

        $scopedContext = null;

        if (array_key_exists(Keywords::CONTEXT, $value)) {
            $scopedContext = $value[Keywords::CONTEXT];

            if (!is_string($scopedContext) && !is_array($scopedContext)) {
                throw new InvalidContextException('invalid scoped context');
            }

            // A scoped context is validated eagerly: an invalid one is an
            // error even when the property is never used. Nullification
            // keeps its own code at the first level and is wrapped in
            // `invalid scoped context` when it surfaces from a nested one.
            try {
                $this->process(
                    $builder->freeze(),
                    $scopedContext,
                    $options->withoutPropagation()->withScoped()->deeper(),
                );
            } catch (InvalidContextException $exception) {
                if ($exception->getMessage() === 'invalid context nullification' && !$options->scoped) {
                    // First-level nullification keeps its own code; nested
                    // ones surface as `invalid scoped context`.
                    throw $exception;
                }

                throw new InvalidContextException('invalid scoped context', previous: $exception);
            }
        }

        $language = null;

        if (array_key_exists(Keywords::LANGUAGE, $value)) {
            $languageValue = $value[Keywords::LANGUAGE];

            if ($languageValue === null) {
                $language = Keywords::NONE;
            } elseif (is_string($languageValue)) {
                $language = mb_strtolower($languageValue);
            } else {
                throw new InvalidContextException('invalid language mapping');
            }
        }

        $direction = null;

        if (array_key_exists(Keywords::DIRECTION, $value)) {
            $directionValue = $value[Keywords::DIRECTION];

            if ($directionValue === null || $directionValue === 'ltr' || $directionValue === 'rtl') {
                $direction = $directionValue;
            } else {
                throw new InvalidContextException('invalid direction mapping');
            }
        }

        $prefix = $simple
            && $colon === false
            && $mapping !== null
            && str_contains(self::GEN_DELIMS, substr($mapping, -1));

        if (array_key_exists(Keywords::PREFIX, $value)) {
            $prefixValue = $value[Keywords::PREFIX];

            $simpleTerm = $colon === false
                && !str_starts_with($term, '.')
                && !str_contains($term, '/');

            if (!is_bool($prefixValue) || ($prefixValue && (!$simpleTerm || $mapping === null))) {
                throw new InvalidContextException('invalid @prefix value');
            }

            $prefix = $prefixValue;
        }

        if (array_key_exists(Keywords::PROTECTED, $value)) {
            if (!is_bool($value[Keywords::PROTECTED])) {
                throw new InvalidContextException('invalid @protected value');
            }

            $protected = $value[Keywords::PROTECTED];
        }

        $nest = null;

        if (array_key_exists(Keywords::NEST, $value)) {
            $nestValue = $value[Keywords::NEST];

            if ($nestValue === null || $nestValue === Keywords::NEST) {
                $nest = $nestValue;
            } elseif (is_string($nestValue) && !Keywords::isKeyword($nestValue) && $builder->keywordFor($nestValue) === null) {
                $nest = $nestValue;
            } else {
                throw new InvalidContextException('invalid @nest value');
            }

            if ($nest !== null && $reverse) {
                throw new InvalidContextException('invalid reverse property');
            }
        }

        if ($keyword !== null) {
            $currentAlias = $builder->aliasFor($keyword);

            if ($currentAlias !== null && $currentAlias !== $term) {
                throw new InvalidContextException('colliding keywords');
            }

            $builder->alias($term, $keyword);
        }

        $builder->setTerm(
            $term,
            new TermDefinition(
                iriMapping: $mapping,
                keyword: $keyword,
                prefix: $prefix,
                protected: $protected,
                reverse: $reverse,
                container: $container,
                index: $index,
                language: $language,
                direction: $direction,
                type: $type,
                nest: $nest,
                localContext: $scopedContext,
            ),
        );

        $defined[$term] = true;
    }

    /**
     * The lazy variant of IRI expansion used while term definitions are
     * being created: a referenced term is defined on demand, with cycle
     * detection through the defined map.
     *
     * @param array<string, mixed> $localContext
     * @param array<string, bool> $defined
     *
     * @return string|null absolute IRI, keyword, blank node identifier or null
     */
    private function expandTermIri(
        ContextBuilder $builder,
        string $value,
        bool $vocab,
        array $localContext,
        array &$defined,
        ProcessOptions $options,
        bool $documentRelative = false,
        bool $skipSelf = false,
    ): ?string {
        if ($value === '') {
            return null;
        }

        if (Keywords::isKeyword($value)) {
            return $value;
        }

        if (!$skipSelf && array_key_exists($value, $localContext)) {
            if (!array_key_exists($value, $defined)) {
                $localValue = $localContext[$value];

                if ($localValue === null) {
                    $defined[$value] = true;
                } else {
                    $this->createTermDefinition($builder, $localContext, $value, $localValue, $defined, false, $options);
                }
            }

            if (isset($defined[$value]) && $defined[$value] === false) {
                // The term is currently being defined: fall through to the
                // compact-IRI path — a self-reference through a valid
                // prefix is legal, anything else is a cycle.
                $colon = strpos($value, ':');

                if ($colon === false || !str_starts_with(substr($value, $colon + 1), '//')) {
                    if ($colon === false) {
                        throw new InvalidContextException('cyclic IRI mapping');
                    }
                }
            }

            $definition = $builder->term($value);

            $mapping = $definition?->keyword ?? $definition?->iriMapping;

            if ($mapping !== null && $documentRelative && !IriExpansion::isAbsolute($mapping)
                && $builder->base() !== null
            ) {
                return (string) $builder->base()->resolve(\Rasuvaeff\JsonLd\Iri::of($mapping));
            }

            return $mapping;
        }

        $definition = $builder->term($value);

        if ($definition !== null) {
            return $definition->keyword ?? $definition->iriMapping;
        }

        if ($builder->isMapped($value)) {
            return null;
        }

        $colon = strpos($value, ':');

        if ($colon !== false) {
            $prefix = substr($value, 0, $colon);
            $suffix = substr($value, $colon + 1);

            if ($prefix === '_') {
                return $value;
            }

            // scheme://… is an absolute IRI: a term named like the scheme
            // is never applied to it.
            if (str_starts_with($suffix, '//')) {
                return $value;
            }

            if (array_key_exists($prefix, $localContext) && !array_key_exists($prefix, $defined) && $localContext[$prefix] !== null) {
                $this->createTermDefinition($builder, $localContext, $prefix, $localContext[$prefix], $defined, false, $options);
            }

            if (isset($defined[$prefix]) && $defined[$prefix] === false) {
                throw new InvalidContextException('cyclic IRI mapping');
            }

            $prefixDefinition = $builder->term($prefix);

            if ($prefixDefinition !== null && $prefixDefinition->prefix && $prefixDefinition->iriMapping !== null) {
                return $prefixDefinition->iriMapping . substr($value, $colon + 1);
            }

            if (IriExpansion::hasValidScheme($prefix)) {
                return $value;
            }
        }

        if ($vocab && $builder->vocab() !== null) {
            return $builder->vocab() . $value;
        }

        if ($documentRelative && $builder->base() !== null) {
            return (string) $builder->base()->resolve(\Rasuvaeff\JsonLd\Iri::of($value));
        }

        return null;
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return list<string>
     */
    private function containerMapping(array $value): array
    {
        if (!array_key_exists('@container', $value)) {
            return [];
        }

        $raw = $value['@container'];

        if ($raw === null) {
            return [];
        }

        $items = is_array($raw) ? $raw : [$raw];
        $container = [];

        foreach ($items as $item) {
            if (!is_string($item) || in_array($item, $container, true)) {
                throw new InvalidContextException('invalid container mapping');
            }

            $container[] = $item;
        }

        sort($container);

        if (!in_array(implode(',', $container), self::CONTAINERS, true)) {
            throw new InvalidContextException('invalid container mapping');
        }

        return $container;
    }

    private function load(string $reference, ProcessOptions $options): \Rasuvaeff\JsonLd\Loader\LoadedContext
    {
        try {
            return $options->loader->load($reference);
        } catch (DocumentLoadingException $exception) {
            throw new InvalidContextException('loading remote context failed', previous: $exception);
        }
    }
}
