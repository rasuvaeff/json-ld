<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd\Context;

use Rasuvaeff\JsonLd\Exception\ContextRecursionException;
use Rasuvaeff\JsonLd\Exception\DocumentLoadingException;
use Rasuvaeff\JsonLd\Exception\ErrorCode;
use Rasuvaeff\JsonLd\Exception\InvalidContextException;
use Rasuvaeff\JsonLd\Exception\JsonLdException;
use Rasuvaeff\JsonLd\Keywords;
use Rasuvaeff\JsonLd\Loader\ContextLoaderInterface;
use Rasuvaeff\JsonLd\Loader\LoadedContext;

/**
 * Context processing and IRI expansion, JSON-LD 1.1 API sections 4.1 to 4.3.
 *
 * Every thrown exception carries the corresponding W3C error code
 * ({@see JsonLdException::errorCode()}, for example `invalid @version value`).
 * Remote contexts are loaded only through the injected loader.
 *
 * @internal
 *
 * @psalm-import-type JsonValue from \Rasuvaeff\JsonLd\Json
 */
final readonly class ContextProcessor
{
    private const int MAX_REMOTE_CONTEXTS = 64;

    private const array CONTEXT_KEYWORDS = [
        '@base',
        '@direction',
        '@import',
        '@language',
        '@propagate',
        '@protected',
        '@version',
        '@vocab',
    ];

    private const array CONTAINER_KEYWORDS = ['@graph', '@id', '@index', '@language', '@list', '@set', '@type'];

    public function __construct(private ContextLoaderInterface $loader) {}

    /**
     * Processes a local context against an active context.
     *
     * @param mixed $localContext context reference, context object, or a list of them
     * @param string|null $baseUrl location the local context was found at
     * @param list<string> $remoteContexts remote contexts being loaded, for cycle detection
     *
     * @throws InvalidContextException with a W3C error code as the message
     * @throws ContextRecursionException when remote contexts include each other
     */
    public function process(
        ActiveContext $activeContext,
        mixed $localContext,
        ?string $baseUrl,
        array $remoteContexts = [],
        bool $overrideProtected = false,
        bool $propagate = true,
        bool $validateScopedContext = true,
    ): ActiveContext {
        $result = clone $activeContext;

        if ($this->isMap($localContext) && array_key_exists('@propagate', $localContext)) {
            if (!is_bool($localContext['@propagate'])) {
                throw new InvalidContextException(ErrorCode::InvalidPropagateValue);
            }

            $propagate = $localContext['@propagate'];
        }

        if (!$propagate && !$result->previous instanceof \Rasuvaeff\JsonLd\Context\ActiveContext) {
            $result->previous = $activeContext;
        }

        $items = is_array($localContext) && array_is_list($localContext) ? $localContext : [$localContext];

        foreach ($items as $context) {
            if ($context === null) {
                if (!$overrideProtected && $activeContext->hasProtectedTerms()) {
                    throw new InvalidContextException(ErrorCode::InvalidContextNullification);
                }

                $previous = $result;
                $result = new ActiveContext(base: $activeContext->originalBase, originalBase: $activeContext->originalBase);

                if (!$propagate) {
                    $result->previous = $previous;
                }

                continue;
            }

            if (is_string($context)) {
                $result = $this->processReference(
                    $result,
                    $context,
                    $baseUrl,
                    $remoteContexts,
                    $overrideProtected,
                    $propagate,
                    $validateScopedContext,
                );

                continue;
            }

            if (!$this->isMap($context)) {
                throw new InvalidContextException(ErrorCode::InvalidLocalContext);
            }

            $result = $this->processObject(
                $result,
                $context,
                $baseUrl,
                $remoteContexts,
                $overrideProtected,
                $validateScopedContext,
            );
        }

        return $result;
    }

    /**
     * IRI expansion, API section 4.3.
     *
     * With a scope, terms of the local context referenced by the value are
     * defined on demand.
     *
     * @throws InvalidContextException
     */
    public function expandIri(
        ActiveContext $activeContext,
        ?string $value,
        bool $vocab = false,
        bool $documentRelative = false,
        ?DefinitionScope $scope = null,
    ): ?string {
        if ($value === null || Keywords::isKeyword($value)) {
            return $value;
        }

        if (Syntax::looksLikeKeyword($value)) {
            return null;
        }

        if ($scope instanceof \Rasuvaeff\JsonLd\Context\DefinitionScope && array_key_exists($value, $scope->local) && ($scope->defined[$value] ?? false) !== true) {
            $this->createTermDefinition($activeContext, $value, $scope);
        }

        if ($vocab) {
            $definition = $activeContext->term($value);

            if ($definition instanceof \Rasuvaeff\JsonLd\Context\TermDefinition) {
                return $definition->iri;
            }
        }

        $colon = strlen($value) > 1 ? strpos($value, ':', 1) : false;

        if ($colon !== false) {
            $prefix = substr($value, 0, $colon);
            $suffix = substr($value, $colon + 1);

            if ($prefix === '_' || str_starts_with($suffix, '//')) {
                return $value;
            }

            if ($scope instanceof \Rasuvaeff\JsonLd\Context\DefinitionScope && array_key_exists($prefix, $scope->local) && ($scope->defined[$prefix] ?? false) !== true) {
                $this->createTermDefinition($activeContext, $prefix, $scope);
            }

            $prefixDefinition = $activeContext->term($prefix);

            if ($prefixDefinition instanceof \Rasuvaeff\JsonLd\Context\TermDefinition && $prefixDefinition->iri !== null && $prefixDefinition->prefix) {
                return $prefixDefinition->iri . $suffix;
            }

            if (Syntax::isAbsoluteIri($value)) {
                return $value;
            }
        }

        if ($vocab && $activeContext->vocab !== null) {
            return $activeContext->vocab . $value;
        }

        if ($documentRelative) {
            return Syntax::resolve($activeContext->base, $value);
        }

        return $value;
    }

    /**
     * @param list<string> $remoteContexts
     */
    private function processReference(
        ActiveContext $result,
        string $reference,
        ?string $baseUrl,
        array $remoteContexts,
        bool $overrideProtected,
        bool $propagate,
        bool $validateScopedContext,
    ): ActiveContext {
        $resolved = Syntax::resolve($baseUrl, $reference);

        if (count($remoteContexts) >= self::MAX_REMOTE_CONTEXTS) {
            throw new ContextRecursionException(ErrorCode::ContextOverflow);
        }

        if (in_array($resolved, $remoteContexts, strict: true)) {
            if (!$validateScopedContext) {
                return $result;
            }

            throw new ContextRecursionException(ErrorCode::RecursiveContextInclusion);
        }

        $document = $this->load($resolved);

        if (!$this->isMap($document->document) || !array_key_exists('@context', $document->document)) {
            throw new InvalidContextException(ErrorCode::InvalidRemoteContext);
        }

        return $this->process(
            $result,
            $document->document['@context'],
            $document->documentUrl !== '' ? $document->documentUrl : $resolved,
            [...$remoteContexts, $resolved],
            $overrideProtected,
            $propagate,
            $validateScopedContext,
        );
    }

    /**
     * @param array<string, mixed> $context
     * @param list<string> $remoteContexts
     */
    private function processObject(
        ActiveContext $result,
        array $context,
        ?string $baseUrl,
        array $remoteContexts,
        bool $overrideProtected,
        bool $validateScopedContext,
    ): ActiveContext {
        if (array_key_exists('@version', $context)) {
            $version = $context['@version'];

            if ((!is_int($version) && !is_float($version)) || (float) $version !== 1.1) {
                throw new InvalidContextException(ErrorCode::InvalidVersionValue);
            }
        }

        if (array_key_exists('@import', $context)) {
            if (!is_string($context['@import'])) {
                throw new InvalidContextException(ErrorCode::InvalidImportValue);
            }

            $imported = $this->load(Syntax::resolve($baseUrl, $context['@import']));

            if (!$this->isMap($imported->document)
                || !array_key_exists('@context', $imported->document)
                || !$this->isMap($imported->document['@context'])
            ) {
                throw new InvalidContextException(ErrorCode::InvalidRemoteContext);
            }

            if (array_key_exists('@import', $imported->document['@context'])) {
                throw new InvalidContextException(ErrorCode::InvalidContextEntry);
            }

            unset($context['@import']);
            $context = [...$imported->document['@context'], ...$context];
        }

        if ($remoteContexts === [] && array_key_exists('@base', $context)) {
            /** @var JsonValue $base */
            $base = $context['@base'];

            if ($base === null) {
                $result->base = null;
            } elseif (is_string($base) && Syntax::isAbsoluteIri($base)) {
                $result->base = $base;
            } elseif (is_string($base) && $result->base !== null) {
                $result->base = Syntax::resolve($result->base, $base);
            } else {
                throw new InvalidContextException(ErrorCode::InvalidBaseIri);
            }
        }

        if (array_key_exists('@vocab', $context)) {
            /** @var JsonValue $vocab */
            $vocab = $context['@vocab'];

            if ($vocab === null) {
                $result->vocab = null;
            } elseif (is_string($vocab)) {
                $expanded = $this->expandIri($result, $vocab, vocab: true, documentRelative: true);

                if ($expanded === null || (!Syntax::isAbsoluteIri($expanded) && !Syntax::isBlankNode($expanded))) {
                    throw new InvalidContextException(ErrorCode::InvalidVocabMapping);
                }

                $result->vocab = $expanded;
            } else {
                throw new InvalidContextException(ErrorCode::InvalidVocabMapping);
            }
        }

        if (array_key_exists('@language', $context)) {
            $language = $context['@language'];

            if ($language !== null && !is_string($language)) {
                throw new InvalidContextException(ErrorCode::InvalidDefaultLanguage);
            }

            $result->language = $language;
        }

        if (array_key_exists('@direction', $context)) {
            $direction = $context['@direction'];

            if (!in_array($direction, [null, 'ltr', 'rtl'], strict: true)) {
                throw new InvalidContextException(ErrorCode::InvalidBaseDirection);
            }

            $result->direction = $direction;
        }

        $protected = false;

        if (array_key_exists('@protected', $context)) {
            if (!is_bool($context['@protected'])) {
                throw new InvalidContextException(ErrorCode::InvalidProtectedValue);
            }

            $protected = $context['@protected'];
        }

        $scope = new DefinitionScope(
            local: $context,
            baseUrl: $baseUrl,
            protected: $protected,
            overrideProtected: $overrideProtected,
            remote: $remoteContexts,
            validateScoped: $validateScopedContext,
        );

        foreach (array_keys($context) as $key) {
            $key = strval($key);

            if (in_array($key, self::CONTEXT_KEYWORDS, strict: true)) {
                continue;
            }

            $this->createTermDefinition($result, $key, $scope);
        }

        return $result;
    }

    /**
     * Create Term Definition, API section 4.2.
     */
    private function createTermDefinition(ActiveContext $active, string $term, DefinitionScope $scope): void
    {
        if (array_key_exists($term, $scope->defined)) {
            if ($scope->defined[$term]) {
                return;
            }

            throw new InvalidContextException(ErrorCode::CyclicIriMapping);
        }

        if ($term === '') {
            throw new InvalidContextException(ErrorCode::InvalidTermDefinition);
        }

        $scope->defined[$term] = false;
        $value = $scope->local[$term];

        if ($term === '@type') {
            if (!$this->isMap($value)) {
                throw new InvalidContextException(ErrorCode::KeywordRedefinition);
            }

            /** @var JsonValue $memberValue */
            foreach ($value as $member => $memberValue) {
                if (($member !== '@container' || $memberValue !== '@set') && $member !== '@protected') {
                    throw new InvalidContextException(ErrorCode::KeywordRedefinition);
                }
            }
        } elseif (Keywords::isKeyword($term)) {
            throw new InvalidContextException(ErrorCode::KeywordRedefinition);
        } elseif (Syntax::looksLikeKeyword($term)) {
            $scope->defined[$term] = true;

            return;
        }

        $previous = $active->terms[$term] ?? null;
        unset($active->terms[$term]);

        $simple = false;

        if ($value === null) {
            $value = ['@id' => null];
        } elseif (is_string($value)) {
            $value = ['@id' => $value];
            $simple = true;
        } elseif (!$this->isMap($value) && $value !== []) {
            throw new InvalidContextException(ErrorCode::InvalidTermDefinition);
        }

        /** @var array<string, mixed> $value */
        $protected = $scope->protected;

        if (array_key_exists('@protected', $value)) {
            if (!is_bool($value['@protected'])) {
                throw new InvalidContextException(ErrorCode::InvalidProtectedValue);
            }

            $protected = $value['@protected'];
        }

        $type = null;

        if (array_key_exists('@type', $value)) {
            $typeValue = $value['@type'];

            if (!is_string($typeValue)) {
                throw new InvalidContextException(ErrorCode::InvalidTypeMapping);
            }

            try {
                $type = in_array($typeValue, ['@id', '@json', '@none', '@vocab'], strict: true)
                    ? $typeValue
                    : $this->expandIri($active, $typeValue, vocab: true, scope: $scope);
            } catch (InvalidContextException) {
                throw new InvalidContextException(ErrorCode::InvalidTypeMapping);
            }

            if ($type === null
                || (!in_array($type, ['@id', '@json', '@none', '@vocab'], strict: true) && !Syntax::isAbsoluteIri($type))
            ) {
                throw new InvalidContextException(ErrorCode::InvalidTypeMapping);
            }
        }

        $reverse = false;
        $reverseContainer = [];
        $iri = null;

        if (array_key_exists('@reverse', $value)) {
            if (array_key_exists('@id', $value) || array_key_exists('@nest', $value)) {
                throw new InvalidContextException(ErrorCode::InvalidReverseProperty);
            }

            $reverseValue = $value['@reverse'];

            if (!is_string($reverseValue)) {
                throw new InvalidContextException(ErrorCode::InvalidIriMapping);
            }

            if (Syntax::looksLikeKeyword($reverseValue)) {
                $scope->defined[$term] = true;

                return;
            }

            $iri = $this->expandIri($active, $reverseValue, vocab: true, scope: $scope);

            if ($iri === null || (!Syntax::isAbsoluteIri($iri) && !Syntax::isBlankNode($iri))) {
                throw new InvalidContextException(ErrorCode::InvalidIriMapping);
            }

            $container = [];

            if (array_key_exists('@container', $value) && $value['@container'] !== null) {
                $containerValue = $value['@container'];

                if ($containerValue !== '@set' && $containerValue !== '@index') {
                    throw new InvalidContextException(ErrorCode::InvalidReverseProperty);
                }

                $container = [$containerValue];
            }

            $reverse = true;
            $reverseContainer = $container;
        }

        $iri ??= null;
        $prefix = false;
        $hasColon = preg_match('~.:.~s', $term) === 1;

        if ($reverse) {
            // The reverse IRI mapping is already set.
        } elseif (array_key_exists('@id', $value) && $value['@id'] !== $term) {
            $id = $value['@id'];

            if ($id !== null) {
                if (!is_string($id)) {
                    throw new InvalidContextException(ErrorCode::InvalidIriMapping);
                }

                if (!Keywords::isKeyword($id) && Syntax::looksLikeKeyword($id)) {
                    $scope->defined[$term] = true;

                    return;
                }

                $iri = $this->expandIri($active, $id, vocab: true, scope: $scope);

                if ($iri === null
                    || (!Keywords::isKeyword($iri) && !Syntax::isAbsoluteIri($iri) && !Syntax::isBlankNode($iri))
                ) {
                    throw new InvalidContextException(ErrorCode::InvalidIriMapping);
                }

                if ($iri === '@context') {
                    throw new InvalidContextException(ErrorCode::InvalidKeywordAlias);
                }

                if ($hasColon || str_contains($term, '/')) {
                    $scope->defined[$term] = true;

                    if ($this->expandIri($active, $term, vocab: true, scope: $scope) !== $iri) {
                        throw new InvalidContextException(ErrorCode::InvalidIriMapping);
                    }
                }

                if (!$hasColon && !str_contains($term, '/') && $simple
                    && (Syntax::endsWithGenDelim($iri) || Syntax::isBlankNode($iri))
                ) {
                    $prefix = true;
                }
            }
        } elseif ($hasColon) {
            $colon = (int) strpos($term, ':', 1);
            $termPrefix = substr($term, 0, $colon);

            if (array_key_exists($termPrefix, $scope->local)) {
                $this->createTermDefinition($active, $termPrefix, $scope);
            }

            $prefixDefinition = $active->term($termPrefix);

            $iri = $prefixDefinition instanceof \Rasuvaeff\JsonLd\Context\TermDefinition && $prefixDefinition->iri !== null
                ? $prefixDefinition->iri . substr($term, $colon + 1)
                : $term;
        } elseif (str_contains($term, '/')) {
            $scope->defined[$term] = true;
            $iri = $this->expandIri($active, $term, vocab: true, scope: $scope);

            if ($iri === null || !Syntax::isAbsoluteIri($iri)) {
                throw new InvalidContextException(ErrorCode::InvalidIriMapping);
            }
        } elseif ($term === '@type') {
            $iri = '@type';
        } elseif ($active->vocab !== null) {
            $iri = $active->vocab . $term;
        } else {
            throw new InvalidContextException(ErrorCode::InvalidIriMapping);
        }

        $container = $reverseContainer;

        if (!$reverse && array_key_exists('@container', $value)) {
            $container = $this->containerMapping($value['@container']);

            if (in_array('@type', $container, strict: true)) {
                if ($type === null) {
                    $type = '@id';
                } elseif ($type !== '@id' && $type !== '@vocab') {
                    throw new InvalidContextException(ErrorCode::InvalidTypeMapping);
                }
            }
        }

        $index = null;

        if (array_key_exists('@index', $value)) {
            if (!in_array('@index', $container, strict: true) || !is_string($value['@index'])) {
                throw new InvalidContextException(ErrorCode::InvalidTermDefinition);
            }

            try {
                $indexIri = $this->expandIri($active, $value['@index'], vocab: true, scope: $scope);
            } catch (InvalidContextException) {
                throw new InvalidContextException(ErrorCode::InvalidTermDefinition);
            }

            if ($indexIri === null || Keywords::isKeyword($indexIri)
                || (!Syntax::isAbsoluteIri($indexIri) && !Syntax::isBlankNode($indexIri))
            ) {
                throw new InvalidContextException(ErrorCode::InvalidTermDefinition);
            }

            $index = $value['@index'];
        }

        $hasContext = array_key_exists('@context', $value);

        if ($hasContext) {
            try {
                $this->process(
                    $active,
                    $value['@context'],
                    $scope->baseUrl,
                    $scope->remote,
                    overrideProtected: true,
                    validateScopedContext: false,
                );
            } catch (JsonLdException $exception) {
                throw new InvalidContextException(ErrorCode::InvalidScopedContext, previous: $exception);
            }
        }

        $hasLanguage = false;
        $language = null;

        if (array_key_exists('@language', $value) && !array_key_exists('@type', $value)) {
            /** @var JsonValue $language */
            $language = $value['@language'];

            if ($language !== null && !is_string($language)) {
                throw new InvalidContextException(ErrorCode::InvalidLanguageMapping);
            }

            $hasLanguage = true;
        }

        $hasDirection = false;
        $direction = null;

        if (array_key_exists('@direction', $value) && !array_key_exists('@type', $value)) {
            /** @var JsonValue $direction */
            $direction = $value['@direction'];

            if (!in_array($direction, [null, 'ltr', 'rtl'], strict: true)) {
                throw new InvalidContextException(ErrorCode::InvalidBaseDirection);
            }

            $hasDirection = true;
        }

        $nest = null;

        if (array_key_exists('@nest', $value)) {
            /** @var JsonValue $nest */
            $nest = $value['@nest'];

            if (!is_string($nest) || ($nest !== '@nest' && Keywords::isKeyword($nest))) {
                throw new InvalidContextException(ErrorCode::InvalidNestValue);
            }
        }

        if (array_key_exists('@prefix', $value)) {
            if ($hasColon || str_contains($term, '/')) {
                throw new InvalidContextException(ErrorCode::InvalidTermDefinition);
            }

            if (!is_bool($value['@prefix'])) {
                throw new InvalidContextException(ErrorCode::InvalidPrefixValue);
            }

            $prefix = $value['@prefix'];

            if ($prefix && $iri !== null && Keywords::isKeyword($iri)) {
                throw new InvalidContextException(ErrorCode::InvalidTermDefinition);
            }
        }

        foreach (array_keys($value) as $member) {
            if (!in_array($member, [
                '@id', '@reverse', '@container', '@context', '@direction', '@index',
                '@language', '@nest', '@prefix', '@protected', '@type',
            ], strict: true)) {
                throw new InvalidContextException(ErrorCode::InvalidTermDefinition);
            }
        }

        $this->define($active, $term, new TermDefinition(
            iri: $iri,
            prefix: $prefix,
            protected: $protected,
            reverse: $reverse,
            container: $container,
            index: $index,
            hasLanguage: $hasLanguage,
            language: $language,
            hasDirection: $hasDirection,
            direction: $direction,
            type: $type,
            nest: $nest,
            hasContext: $hasContext,
            context: $hasContext ? $value['@context'] : null,
            baseUrl: $scope->baseUrl,
        ), $previous, $scope);
    }

    private function define(
        ActiveContext $active,
        string $term,
        TermDefinition $definition,
        ?TermDefinition $previous,
        DefinitionScope $scope,
    ): void {
        if (!$scope->overrideProtected && $previous instanceof \Rasuvaeff\JsonLd\Context\TermDefinition && $previous->protected) {
            if (!$definition->sameAs($previous)) {
                throw new InvalidContextException(ErrorCode::ProtectedTermRedefinition);
            }

            $definition = $previous;
        }

        $active->terms[$term] = $definition;
        $scope->defined[$term] = true;
    }

    /**
     * @return list<string> sorted container keywords
     */
    private function containerMapping(mixed $raw): array
    {
        if ($raw === null) {
            return [];
        }

        $items = is_array($raw) ? $raw : [$raw];

        if (!array_is_list($items)) {
            throw new InvalidContextException(ErrorCode::InvalidContainerMapping);
        }

        $keywords = [];

        foreach ($items as $item) {
            if (!is_string($item) || !in_array($item, self::CONTAINER_KEYWORDS, strict: true)) {
                throw new InvalidContextException(ErrorCode::InvalidContainerMapping);
            }

            $keywords[] = $item;
        }

        if (count(array_unique($keywords)) !== count($keywords)) {
            throw new InvalidContextException(ErrorCode::InvalidContainerMapping);
        }

        sort($keywords);
        $items = $keywords;
        $key = implode(',', $items);

        $valid = count($items) === 1
            || in_array($key, ['@graph,@id', '@graph,@index', '@graph,@set', '@graph,@id,@set', '@graph,@index,@set'], strict: true)
            || in_array($key, ['@id,@set', '@index,@set', '@language,@set', '@set,@type'], strict: true);

        if (!$valid) {
            throw new InvalidContextException(ErrorCode::InvalidContainerMapping);
        }

        return $items;
    }

    /**
     * Whatever the loader throws, the caller sees one failure: `loading
     * remote context failed`, with the loader's exception as the previous one.
     *
     * @throws DocumentLoadingException
     */
    private function load(string $reference): LoadedContext
    {
        try {
            return $this->loader->load($reference);
        } catch (\Throwable $exception) {
            throw new DocumentLoadingException(
                ErrorCode::LoadingRemoteContextFailed,
                sprintf('loading remote context failed: "%s": %s', $reference, $exception->getMessage()),
                $exception,
            );
        }
    }

    /**
     * @psalm-assert-if-true array<string, mixed> $value
     */
    private function isMap(mixed $value): bool
    {
        return is_array($value) && !array_is_list($value);
    }
}
