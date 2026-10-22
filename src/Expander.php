<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd;

use Rasuvaeff\JsonLd\Context\ActiveContext;
use Rasuvaeff\JsonLd\Context\ContextProcessor;
use Rasuvaeff\JsonLd\Context\IriExpansion;
use Rasuvaeff\JsonLd\Context\ProcessOptions;
use Rasuvaeff\JsonLd\Context\TermDefinition;
use Rasuvaeff\JsonLd\Exception\InvalidContextException;
use Rasuvaeff\JsonLd\Exception\JsonLdException;
use Rasuvaeff\JsonLd\Loader\LoadedContext;

/**
 * Expansion, JSON-LD 1.1 API section 5.1.3: turns compact documents into
 * the expanded form.
 *
 * Every thrown exception message equals the corresponding JSON-LD error
 * code from the W3C test suite.
 *
 * @api
 */
final class Expander
{
    public function __construct(private readonly ContextProcessor $processor = new ContextProcessor()) {}

    /**
     * Expands a document given as decoded JSON or as an inline JSON string.
     *
     * @param string|array<mixed> $input
     *
     * @return list<mixed> the expanded form, always a list of node/value objects
     *
     * @throws JsonLdException with a W3C error code as the message
     */
    public function expand(string|array $input, ?ExpandOptions $options = null): array
    {
        $options ??= new ExpandOptions();

        $context = new ActiveContext(base: $options->base !== null ? Iri::of($options->base) : null);
        $processOptions = new ProcessOptions(loader: $options->loader, base: $context->base);

        if ($options->expandContext !== null) {
            $context = $this->processor->process($context, $options->expandContext, $processOptions);
        }

        if (is_string($input)) {
            $loaded = $this->load($input, $options);
            $input = $loaded->document;

            if ($loaded->documentUrl !== '' && $context->base === null) {
                $context = new ActiveContext(base: Iri::of($loaded->documentUrl));
            }

            $processOptions = new ProcessOptions(loader: $options->loader, base: $context->base);
        }

        // The document's own @context is processed when the top-level node
        // object is expanded; pre-processing here would apply it twice.

        $expanded = $this->expandElement($context, null, null, $input, new ExpansionState(loader: $options->loader));

        if ($expanded === null) {
            return [];
        }

        // A single node object holding only @graph is replaced by its graph.
        if (is_array($expanded) && !array_is_list($expanded)
            && count($expanded) === 1
            && array_key_exists(Keywords::GRAPH, $expanded)
        ) {
            $graph = $expanded[Keywords::GRAPH];

            return is_array($graph) && array_is_list($graph) ? $graph : [$graph];
        }

        return is_array($expanded) && array_is_list($expanded) ? $expanded : [$expanded];
    }

    /**
     * The recursive expansion of an element, JSON-LD 1.1 API section 5.1.3.
     *
     * @param string|null $activeProperty expanded property the element is a value of
     * @param TermDefinition|null $termDefinition definition of the original (unexpanded) key
     * @param mixed $element
     *
     * @return mixed expanded fragment: list, node object, value object or null (dropped)
     */
    private function expandElement(
        ActiveContext $context,
        ?string $activeProperty,
        ?TermDefinition $termDefinition,
        mixed $element,
        ExpansionState $state,
    ): mixed {
        if (is_array($element) && array_is_list($element)) {
            $result = [];

            foreach ($element as $item) {
                $expanded = $this->expandElement($context, $activeProperty, $termDefinition, $item, $state);

                if ($expanded === null) {
                    continue;
                }

                if (is_array($expanded) && array_is_list($expanded)) {
                    $result = [...$result, ...$expanded];
                } else {
                    $result[] = $expanded;
                }
            }

            return $result;
        }

        if (!is_array($element)) {
            return $this->expandScalar($context, $activeProperty, $termDefinition, $element);
        }

        if ($element === []) {
            return [];
        }

        return $this->expandNodeObject($context, $activeProperty, $termDefinition, $element, $state);
    }

    /**
     * @param array<string, mixed> $element
     *
     * @return array<string, mixed>|null
     */
    private function expandNodeObject(
        ActiveContext $context,
        ?string $activeProperty,
        ?TermDefinition $termDefinition,
        array $element,
        ExpansionState $state,
    ): ?array {
        // The inside flags matter only for this exact object; nested members
        // start with a clean state.
        $objectFlags = $state;
        $state = new ExpansionState(loader: $state->loader);

        if (array_key_exists(Keywords::CONTEXT, $element)) {
            $context = $this->processor->process(
                $context,
                $element[Keywords::CONTEXT],
                new ProcessOptions(loader: $state->loader, base: $context->base),
            );
        }

        // A non-propagating (scoped) context applies to this object only.
        // Entering a node with real properties reverts to the previous
        // context; bare `@id` references and value objects inherit the
        // scoped one. Type-scoped contexts activate before the members,
        // in `@type` order.
        $typeKey = $this->aliasKey($context, $element, Keywords::TYPE);

        $hasPropertyMember = (bool) array_filter(
            array_keys($element),
            static function (string $key) use ($context): bool {
                $keyword = Keywords::isKeyword($key) ? $key : $context->keywordFor($key);

                return $keyword === null;
            },
        );

        if ($context->previousContext !== null && $hasPropertyMember) {
            $context = $context->previousContext;
        }

        if ($typeKey !== null) {
            $rawTypes = is_array($element[$typeKey]) && array_is_list($element[$typeKey])
                ? $element[$typeKey]
                : [$element[$typeKey]];

            foreach ($rawTypes as $rawType) {
                if (!is_string($rawType)) {
                    continue;
                }

                $typeTerm = $this->lookupTerm($context, $rawType);

                if ($typeTerm?->localContext !== null) {
                    $context = $this->processor->process(
                        $context,
                        $typeTerm->localContext,
                        new ProcessOptions(loader: $state->loader, base: $context->base, propagate: false),
                    );
                }
            }
        }

        $valueKey = $this->aliasKey($context, $element, Keywords::VALUE);

        if ($valueKey !== null) {
            if ($activeProperty === null || $activeProperty === Keywords::GRAPH) {
                // A free-floating value object carries no meaning.
                return null;
            }

            return $this->expandValueObject($context, $element, $valueKey);
        }

        /** @var array<string, mixed> $result */
        $result = [];
        /** @var list<string> $nests */
        $nests = [];

        $hasListMember = $this->aliasKey($context, $element, Keywords::LIST) !== null;
        $hasSetMember = $this->aliasKey($context, $element, Keywords::SET) !== null;

        $setKey = $this->aliasKey($context, $element, Keywords::SET);

        if ($setKey !== null) {
            // A set object expands to just its contents.
            $others = array_keys($element);

            $allowed = array_map(
                static fn (string $key): ?string => Keywords::isKeyword($key)
                    ? $key
                    : $context->keywordFor($key),
                $others,
            );

            $onlySet = array_reduce(
                $others,
                static function (bool $carry, string $key) use ($context): bool {
                    $keyword = Keywords::isKeyword($key) ? $key : $context->keywordFor($key);

                    return $carry && in_array($keyword, [Keywords::SET, Keywords::INDEX, Keywords::CONTEXT], true);
                },
                true,
            );

            if ($onlySet) {
                unset($allowed);

                $setExpanded = $this->expandElement(
                    $context,
                    $activeProperty,
                    $termDefinition,
                    $element[$setKey],
                    new ExpansionState(loader: $state->loader),
                );

                if ($setExpanded === null) {
                    return null;
                }

                return is_array($setExpanded) && array_is_list($setExpanded)
                    ? $setExpanded
                    : [$setExpanded];
            }

            unset($allowed);
        }

        if ($hasListMember && $hasSetMember) {
            throw new InvalidContextException('invalid set or list object');
        }

        if ($hasListMember) {
            foreach ($element as $key => $_) {
                $memberKeyword = Keywords::isKeyword($key)
                    ? $key
                    : $context->keywordFor((string) $key);

                if ($memberKeyword !== null && !in_array($memberKeyword, [Keywords::LIST, Keywords::INDEX], true)) {
                    throw new InvalidContextException('invalid set or list object');
                }
            }
        }

        foreach ($element as $key => $value) {
            $keyword = Keywords::isKeyword($key)
                ? $key
                : $context->keywordFor((string) $key);

            if ($keyword !== null) {
                if ($keyword === Keywords::CONTEXT) {
                    continue;
                }

                if ($keyword === Keywords::NEST) {
                    $nests[] = $key;

                    continue;
                }

                $this->expandKeywordMember(
                    $context,
                    $keyword,
                    $value,
                    $result,
                    $objectFlags,
                    $activeProperty,
                    $termDefinition,
                );

                continue;
            }

            $this->expandPropertyMember(
                $context,
                $key,
                $value,
                $result,
                $state,
            );
        }

        foreach ($nests as $nestKey) {
            if (!is_array($element[$nestKey]) || array_is_list($element[$nestKey])) {
                throw new InvalidContextException('invalid @nest value');
            }

            $this->expandNest($context, $element, $nestKey, $result, $state);
        }

        // Free-floating nodes: an object that is empty or holds nothing but
        // @id at the node level is dropped.
        if (($activeProperty === null || $activeProperty === Keywords::GRAPH)
            && ($result === [] || (count($result) === 1 && array_key_exists(Keywords::ID, $result)))
        ) {
            return null;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $result
     */
    private function expandKeywordMember(
        ActiveContext &$context,
        string $keyword,
        mixed $value,
        array &$result,
        ExpansionState $state,
        ?string $activeProperty,
        ?TermDefinition $termDefinition,
    ): void {
        switch ($keyword) {
            case Keywords::ID:
                if ($value === null) {
                    break;
                }

                if (!is_string($value)) {
                    throw new InvalidContextException('invalid @id value');
                }

                $expanded = $value === '' && $context->base !== null
                    ? (string) $context->base
                    : IriExpansion::expand($context, $value, documentRelative: true);

                if ($expanded === null || $expanded === '') {
                    throw new InvalidContextException('invalid @id value');
                }

                $this->addValue($result, Keywords::ID, $expanded, false);

                break;

            case Keywords::TYPE:
                $types = is_array($value) ? $value : [$value];
                $expandedTypes = [];

                foreach ($types as $type) {
                    if (!is_string($type)) {
                        throw new InvalidContextException('invalid type value');
                    }

                    $expandedType = Keywords::isKeyword($type)
                        ? $type
                        : IriExpansion::expand($context, $type, vocab: true, documentRelative: true);

                    if ($expandedType === null || (!IriExpansion::isAbsolute($expandedType) && !str_starts_with($expandedType, '_:'))) {
                        throw new InvalidContextException('invalid type value');
                    }

                    if (!in_array($expandedType, $expandedTypes, true)) {
                        $expandedTypes[] = $expandedType;
                    }
                }

                $this->addValue($result, Keywords::TYPE, $expandedTypes, true);

                break;

            case Keywords::GRAPH:
                $expanded = $this->expandElement($context, Keywords::GRAPH, null, $value, new ExpansionState(loader: $state->loader));

                $this->addValue(
                    $result,
                    Keywords::GRAPH,
                    is_array($expanded) && array_is_list($expanded) ? $expanded : [$expanded],
                    true,
                );

                break;

            case Keywords::INCLUDED:
                $expanded = $this->expandElement($context, null, null, $value, new ExpansionState(loader: $state->loader));

                foreach (is_array($expanded) && array_is_list($expanded) ? $expanded : [$expanded] as $node) {
                    if (!is_array($node) || array_is_list($node)) {
                        throw new InvalidContextException('invalid @included value');
                    }

                    $this->addValue($result, Keywords::INCLUDED, $node, true);
                }

                break;

            case Keywords::LIST:
                if ($state->insideList) {
                    throw new InvalidContextException('list of lists');
                }

                $expanded = $this->expandElement(
                    $context,
                    $activeProperty,
                    $termDefinition,
                    $value,
                    $state->withInsideList(),
                );

                $this->addValue(
                    $result,
                    Keywords::LIST,
                    is_array($expanded) && array_is_list($expanded) ? $expanded : [$expanded],
                    true,
                );

                break;

            case Keywords::SET:
                $expanded = $this->expandElement($context, $activeProperty, $termDefinition, $value, new ExpansionState(loader: $state->loader));

                if ($expanded !== null) {
                    $this->addValue($result, (string) $activeProperty, $expanded, true);
                }

                break;

            case Keywords::REVERSE:
                $this->expandReverse($context, $value, $result, $state);

                break;

            case Keywords::INDEX:
                if (!is_string($value)) {
                    throw new InvalidContextException('invalid @index value');
                }

                if (array_key_exists(Keywords::INDEX, $result) && $result[Keywords::INDEX] !== $value) {
                    throw new InvalidContextException('invalid @index value');
                }

                $this->addValue($result, Keywords::INDEX, $value, false);

                break;

            default:
                // @value / @language / @direction / @type as node members are
                // handled by the value-object branch; anything else is dropped.
                break;
        }
    }

    /**
     * @param array<string, mixed> $value
     * @param array<string, mixed> $result
     */
    private function expandReverse(ActiveContext $context, mixed $value, array &$result, ExpansionState $state): void
    {
        if (!is_array($value) || array_is_list($value)) {
            throw new InvalidContextException('invalid @reverse value');
        }

        if (array_key_exists(Keywords::CONTEXT, $value)) {
            $context = $this->processor->process(
                $context,
                $value[Keywords::CONTEXT],
                new ProcessOptions(loader: $state->loader, base: $context->base),
            );
        }

        /** @var array<string, mixed> $reverseMap */
        $reverseMap = [];

        foreach ($value as $property => $propertyValue) {
            if ($property === Keywords::CONTEXT) {
                continue;
            }

            $keyword = Keywords::isKeyword($property)
                ? $property
                : $context->keywordFor((string) $property);

            if ($keyword !== null) {
                throw new InvalidContextException('invalid reverse property map');
            }

            $expandedProperty = IriExpansion::expand($context, $property, vocab: true);

            if ($expandedProperty === null
                || (!IriExpansion::isAbsolute($expandedProperty) && !str_starts_with($expandedProperty, '_:'))
            ) {
                continue;
            }

            $expanded = $this->expandElement(
                $context,
                $expandedProperty,
                $this->lookupTerm($context, (string) $property),
                $propertyValue,
                new ExpansionState(loader: $state->loader, insideReverse: true),
            );

            foreach (is_array($expanded) && array_is_list($expanded) ? $expanded : [$expanded] as $node) {
                if (!is_array($node) || array_is_list($node) || array_key_exists(Keywords::VALUE, $node)) {
                    throw new InvalidContextException('invalid reverse property value');
                }

                $this->addValue($reverseMap, $expandedProperty, $node, true);
            }
        }

        foreach ($reverseMap as $property => $propertyValue) {
            $this->addValue($result, $property, $propertyValue, true);
        }
    }

    /**
     * @param array<string, mixed> $result
     */
    private function expandPropertyMember(
        ActiveContext $context,
        string $key,
        mixed $value,
        array &$result,
        ExpansionState $state,
    ): void {
        $termDefinition = $this->lookupTerm($context, (string) $key);

        $expandedProperty = IriExpansion::expand($context, $key, vocab: true);

        if ($expandedProperty === null
            || (!IriExpansion::isAbsolute($expandedProperty) && !str_starts_with($expandedProperty, '_:'))
        ) {
            return;
        }

        // Property-scoped context.
        if ($termDefinition?->localContext !== null) {
            $context = $this->processor->process(
                $context,
                $termDefinition->localContext,
                new ProcessOptions(loader: $state->loader, base: $context->base),
            );

            $termDefinition = $this->lookupTerm($context, (string) $key);
        }

        if ($value === null) {
            return;
        }

        $container = $termDefinition->container ?? [];
        $expandedValue = null;

        if (in_array(Keywords::GRAPH, $container, true)) {
            $expandedValue = $this->expandGraphContainer($context, $expandedProperty, $termDefinition, $value, $container, $state);
        } elseif (in_array(Keywords::LANGUAGE, $container, true) && is_array($value) && !array_is_list($value)) {
            $expandedValue = $this->expandLanguageMap($context, $expandedProperty, $termDefinition, $value, $state);
        } elseif (in_array(Keywords::INDEX, $container, true) && is_array($value) && !array_is_list($value)) {
            $expandedValue = $this->expandIndexMap($context, $expandedProperty, $termDefinition, $value, Keywords::INDEX, $state);
        } elseif (in_array(Keywords::ID, $container, true) && is_array($value) && !array_is_list($value)) {
            $expandedValue = $this->expandIdMap($context, $expandedProperty, $termDefinition, $value, $state);
        } elseif (in_array(Keywords::TYPE, $container, true) && is_array($value) && !array_is_list($value)) {
            $expandedValue = $this->expandTypeMap($context, $expandedProperty, $termDefinition, $value, $state);
        } elseif (false) {
        } else {
            if (is_array($value) && array_is_list($value)) {
                if (in_array(Keywords::LIST, $container, true)) {
                    $expandedValue = [
                        Keywords::LIST => $this->expandElement(
                            $context,
                            $expandedProperty,
                            $termDefinition,
                            $value,
                            new ExpansionState(loader: $state->loader, insideList: true),
                        ),
                    ];
                } elseif ($value === []) {
                    $expandedValue = [];
                } else {
                    $expandedValue = [];

                    foreach ($value as $item) {
                        if ($item === null) {
                            continue;
                        }

                        $expandedItem = $this->expandElement($context, $expandedProperty, $termDefinition, $item, $state);

                        if ($expandedItem === null) {
                            continue;
                        }

                        if (is_array($expandedItem) && array_is_list($expandedItem)) {
                            $expandedValue = [...$expandedValue, ...$expandedItem];
                        } else {
                            $expandedValue[] = $expandedItem;
                        }
                    }
                }
            } elseif (in_array(Keywords::LIST, $container, true)) {
                $expandedValue = [
                    Keywords::LIST => $this->expandElement(
                        $context,
                        $expandedProperty,
                        $termDefinition,
                        [$value],
                        new ExpansionState(loader: $state->loader, insideList: true),
                    ),
                ];
            } else {
                $expandedValue = $this->expandElement($context, $expandedProperty, $termDefinition, $value, $state);
            }
        }

        if ($expandedValue === null) {
            return;
        }

        if ($termDefinition?->reverse) {
            foreach (is_array($expandedValue) && array_is_list($expandedValue) ? $expandedValue : [$expandedValue] as $node) {
                if (!is_array($node) || array_is_list($node)) {
                    throw new InvalidContextException('invalid reverse property value');
                }
            }

            /** @var array<string, mixed> $reverse */
            $reverse = $result[Keywords::REVERSE] ?? [];
            $this->addValue($reverse, $expandedProperty, $expandedValue, true);
            $result[Keywords::REVERSE] = $reverse;

            return;
        }

        // In the expanded form every property value is an array.
        $this->addValue($result, $expandedProperty, $expandedValue, true);
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return list<mixed>
     */
    private function expandLanguageMap(
        ActiveContext $context,
        string $expandedProperty,
        ?TermDefinition $termDefinition,
        array $value,
        ExpansionState $state,
    ): array {
        $result = [];

        foreach ($value as $language => $items) {
            if ($items === null || Keywords::isKeyword((string) $language) || str_starts_with((string) $language, '@')) {
                continue;
            }

            foreach (is_array($items) && array_is_list($items) ? $items : [$items] as $item) {
                $expanded = $this->expandElement($context, $expandedProperty, $termDefinition, $item, new ExpansionState(loader: $state->loader));

                if ($expanded === null) {
                    continue;
                }

                if (!is_array($expanded) || array_is_list($expanded)
                    || !array_key_exists(Keywords::VALUE, $expanded)
                    || !is_string($expanded[Keywords::VALUE])
                ) {
                    throw new InvalidContextException('invalid language map value');
                }

                $expanded[Keywords::LANGUAGE] = mb_strtolower((string) $language);
                $result[] = $expanded;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return list<mixed>
     */
    private function expandIndexMap(
        ActiveContext $context,
        string $expandedProperty,
        ?TermDefinition $termDefinition,
        array $value,
        string $keyword,
        ExpansionState $state,
    ): array {
        $result = [];

        foreach ($value as $index => $items) {
            if ($items === null) {
                continue;
            }

            foreach (is_array($items) && array_is_list($items) ? $items : [$items] as $item) {
                $expanded = $this->expandElement($context, $expandedProperty, $termDefinition, $item, new ExpansionState(loader: $state->loader, insideIndex: true));

                if ($expanded === null) {
                    continue;
                }

                if (is_array($expanded) && !array_is_list($expanded)) {
                    if (array_key_exists($keyword, $expanded) && $expanded[$keyword] !== $index) {
                        throw new InvalidContextException('conflicting indexes');
                    }

                    $expanded[$keyword] = $index;
                }

                $result[] = $expanded;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return list<mixed>
     */
    private function expandIdMap(
        ActiveContext $context,
        string $expandedProperty,
        ?TermDefinition $termDefinition,
        array $value,
        ExpansionState $state,
    ): array {
        $result = [];

        foreach ($value as $id => $items) {
            if ($items === null) {
                continue;
            }

            $expandedId = IriExpansion::expand($context, (string) $id, documentRelative: true);

            foreach (is_array($items) && array_is_list($items) ? $items : [$items] as $item) {
                $expanded = $this->expandElement($context, $expandedProperty, $termDefinition, $item, new ExpansionState(loader: $state->loader));

                if ($expanded === null) {
                    continue;
                }

                if (is_array($expanded) && array_is_list($expanded)) {
                    foreach ($expanded as $node) {
                        if (is_array($node) && !array_is_list($node)) {
                            $node[Keywords::ID] = $expandedId;
                        }

                        $result[] = $node;
                    }
                } else {
                    $expanded[Keywords::ID] = $expandedId;
                    $result[] = $expanded;
                }
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return list<mixed>
     */
    private function expandTypeMap(
        ActiveContext $context,
        string $expandedProperty,
        ?TermDefinition $termDefinition,
        array $value,
        ExpansionState $state,
    ): array {
        $result = [];

        foreach ($value as $type => $items) {
            if ($items === null) {
                continue;
            }

            $scoped = $context;
            $typeTerm = $this->lookupTerm($context, (string) $type);

            if ($typeTerm?->localContext !== null) {
                $scoped = $this->processor->process(
                    $context,
                    $typeTerm->localContext,
                    new ProcessOptions(loader: $state->loader, base: $context->base),
                );
            }

            $expandedType = IriExpansion::expand($context, (string) $type, vocab: true);

            foreach (is_array($items) && array_is_list($items) ? $items : [$items] as $item) {
                $expanded = $this->expandElement($scoped, $expandedProperty, $termDefinition, $item, new ExpansionState(loader: $state->loader));

                if ($expanded === null) {
                    continue;
                }

                if (is_array($expanded) && array_is_list($expanded)) {
                    foreach ($expanded as $node) {
                        if (is_array($node) && !array_is_list($node)) {
                            $this->addValue($node, Keywords::TYPE, $expandedType, true);
                        }

                        $result[] = $node;
                    }
                } elseif (is_array($expanded)) {
                    $this->addValue($expanded, Keywords::TYPE, $expandedType, true);
                    $result[] = $expanded;
                }
            }
        }

        return $result;
    }

    /**
     * @param mixed $value
     *
     * @return list<mixed>
     */
    private function expandGraphContainer(
        ActiveContext $context,
        string $expandedProperty,
        ?TermDefinition $termDefinition,
        mixed $value,
        array $container,
        ExpansionState $state,
    ): array {
        $result = [];

        $wrap = function (mixed $items, ?string $key) use ($context, $container, $state): array {
            $node = [];

            if (in_array(Keywords::ID, $container, true) && $key !== null) {
                $node[Keywords::ID] = IriExpansion::expand($context, $key, documentRelative: true);
            }

            if (in_array(Keywords::INDEX, $container, true) && $key !== null) {
                $node[Keywords::INDEX] = $key;
            }

            if (in_array(Keywords::TYPE, $container, true) && $key !== null) {
                $this->addValue($node, Keywords::TYPE, IriExpansion::expand($context, $key, vocab: true), true);
            }

            $expanded = $this->expandElement($context, Keywords::GRAPH, null, $items, new ExpansionState(loader: $state->loader));

            if ($expanded === null) {
                return [];
            }

            $node[Keywords::GRAPH] = is_array($expanded) && array_is_list($expanded) ? $expanded : [$expanded];

            return $node;
        };

        if (is_array($value) && !array_is_list($value)) {
            foreach ($value as $key => $items) {
                $node = $wrap($items, (string) $key);

                if ($node !== []) {
                    $result[] = $node;
                }
            }

            return $result;
        }

        $items = is_array($value) && array_is_list($value) ? $value : [$value];

        foreach ($items as $item) {
            if ($item === null) {
                continue;
            }

            $node = $wrap($item, null);

            if ($node !== []) {
                $result[] = $node;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $element
     * @param array<string, mixed> $result
     */
    private function expandNest(
        ActiveContext $context,
        array $element,
        string $nestKey,
        array &$result,
        ExpansionState $state,
    ): void {
        $nested = $element[$nestKey];

        foreach (is_array($nested) && array_is_list($nested) ? $nested : [$nested] as $nestedValue) {
            if (!is_array($nestedValue) || array_is_list($nestedValue)) {
                throw new InvalidContextException('invalid @nest value');
            }

            foreach ($nestedValue as $key => $value) {
                $keyword = Keywords::isKeyword($key)
                    ? $key
                    : $context->keywordFor((string) $key);

                if ($keyword !== null && $keyword !== Keywords::NONE) {
                    throw new InvalidContextException('invalid @nest value');
                }

                if ($keyword === Keywords::NONE) {
                    continue;
                }

                $this->expandPropertyMember($context, $key, $value, $result, $state);
            }
        }
    }

    /**
     * @param array<string, mixed> $element
     *
     * @return array<string, mixed>|null
     */
    private function expandValueObject(ActiveContext $context, array $element, string $valueKey): ?array
    {
        $allowed = [
            Keywords::VALUE,
            Keywords::TYPE,
            Keywords::LANGUAGE,
            Keywords::DIRECTION,
            Keywords::INDEX,
        ];

        foreach (array_keys($element) as $key) {
            $keyword = Keywords::isKeyword($key)
                ? $key
                : $context->keywordFor((string) $key);

            if ($keyword === null || !in_array($keyword, $allowed, true)) {
                throw new InvalidContextException('invalid value object');
            }
        }

        $value = $element[$valueKey];

        $result = [];

        $typeKey = $this->aliasKey($context, $element, Keywords::TYPE);

        if ($typeKey !== null) {
            $types = $element[$typeKey];
            $expandedTypes = [];

            foreach (is_array($types) && array_is_list($types) ? $types : [$types] as $type) {
                if (!is_string($type)) {
                    throw new InvalidContextException('invalid typed value');
                }

                $expandedType = $type === Keywords::JSON
                    ? $type
                    : IriExpansion::expand($context, $type, vocab: true);

                if ($expandedType === null || (!IriExpansion::isAbsolute($expandedType) && $expandedType !== Keywords::JSON)) {
                    throw new InvalidContextException('invalid typed value');
                }

                if (!in_array($expandedType, $expandedTypes, true)) {
                    $expandedTypes[] = $expandedType;
                }
            }

            if ($expandedTypes !== []) {
                $result[Keywords::TYPE] = count($expandedTypes) === 1 ? $expandedTypes[0] : $expandedTypes;
            }
        }

        $isJson = isset($result[Keywords::TYPE]) && $result[Keywords::TYPE] === Keywords::JSON;

        if (!$isJson && (is_array($value) || ($value !== null && !is_scalar($value)))) {
            throw new InvalidContextException('invalid value object value');
        }

        if ($value === null) {
            return null;
        }

        $result[Keywords::VALUE] = $value;

        if (isset($result[Keywords::TYPE]) && $result[Keywords::TYPE] !== Keywords::JSON) {
            // @type together with @language or @direction is invalid.
            if ($this->aliasKey($context, $element, Keywords::LANGUAGE) !== null
                || $this->aliasKey($context, $element, Keywords::DIRECTION) !== null
            ) {
                throw new InvalidContextException('invalid value object');
            }
        }

        $languageKey = $this->aliasKey($context, $element, Keywords::LANGUAGE);

        if ($languageKey !== null) {
            if (!is_string($element[$languageKey])) {
                throw new InvalidContextException('invalid language-tagged string');
            }

            if (!is_string($value)) {
                throw new InvalidContextException('invalid language-tagged value');
            }

            $result[Keywords::LANGUAGE] = mb_strtolower($element[$languageKey]);
        }

        if ($languageKey !== null) {
            if (!is_string($element[$languageKey])) {
                throw new InvalidContextException('invalid language tagging');
            }

            $result[Keywords::LANGUAGE] = mb_strtolower($element[$languageKey]);
        }

        $directionKey = $this->aliasKey($context, $element, Keywords::DIRECTION);

        if ($directionKey !== null) {
            $direction = $element[$directionKey];

            if ($direction !== 'ltr' && $direction !== 'rtl') {
                throw new InvalidContextException('invalid base direction');
            }

            $result[Keywords::DIRECTION] = $direction;
        }

        $indexKey = $this->aliasKey($context, $element, Keywords::INDEX);

        if ($indexKey !== null) {
            if (!is_string($element[$indexKey])) {
                throw new InvalidContextException('invalid @index value');
            }

            $result[Keywords::INDEX] = $element[$indexKey];
        }

        return $result;
    }

    /**
     * Expands a scalar leaf into a value object using the term's coercion
     * and the context defaults.
     */
    private function expandScalar(
        ActiveContext $context,
        ?string $activeProperty,
        ?TermDefinition $termDefinition,
        mixed $value,
    ): mixed {
        if ($activeProperty === null || $activeProperty === Keywords::GRAPH) {
            return null;
        }

        if ($value === null) {
            return null;
        }

        $type = $termDefinition->type ?? null;

        if ($type === Keywords::JSON) {
            return [Keywords::VALUE => $value, Keywords::TYPE => Keywords::JSON];
        }

        if ($type !== null && $type !== Keywords::JSON) {
            if ($type === Keywords::ID || $type === Keywords::VOCAB) {
                if (is_string($value)) {
                    // @id/@vocab coercion turns a string into a node
                    // reference; an empty string resolves to the base under
                    // @id, and to nothing when there is no base.
                    if ($value === '' && $type === Keywords::ID && $context->base !== null) {
                        return [Keywords::ID => (string) $context->base];
                    }

                    $expanded = $type === Keywords::ID
                        ? IriExpansion::expand($context, $value, documentRelative: true)
                        : IriExpansion::expand($context, $value, vocab: true);

                    return $expanded === null || $expanded === '' ? [] : [Keywords::ID => $expanded];
                }

                throw new InvalidContextException('invalid typed value');
            }

            if (is_scalar($value)) {
                return [Keywords::VALUE => $value, Keywords::TYPE => $type];
            }

            throw new InvalidContextException('invalid typed value');
        }

        $result = [Keywords::VALUE => $value];

        $language = $termDefinition->language ?? $context->language;

        if (is_string($value) && $language !== null && $language !== Keywords::NONE) {
            $result[Keywords::LANGUAGE] = $language;
        }

        $direction = $termDefinition->direction ?? $context->direction;

        if ($direction !== null) {
            $result[Keywords::DIRECTION] = $direction;
        }

        return $result;
    }

    /**
     * Activates scoped contexts attached to the values of `@type`.
     */
    private function applyTypeScopedContexts(ActiveContext $context, mixed $types, ExpansionState $state): ActiveContext
    {
        foreach (is_array($types) && array_is_list($types) ? $types : [$types] as $type) {
            if (!is_string($type)) {
                continue;
            }

            $termDefinition = $this->lookupTerm($context, $type);

            if ($termDefinition?->localContext !== null) {
                $context = $this->processor->process(
                    $context,
                    $termDefinition->localContext,
                    new ProcessOptions(loader: $state->loader, base: $context->base),
                );
            }
        }

        return $context;
    }

    /**
     * Term lookup that falls back to the previous context chain, which
     * non-propagating scoped contexts rely on.
     */
    private function lookupTerm(ActiveContext $context, string $term): ?TermDefinition
    {
        for ($current = $context; $current !== null; $current = $current->previousContext) {
            if ($current->isMapped($term)) {
                return $current->term($term);
            }
        }

        return null;
    }

    /**
     * Finds the raw key of `$element` that is `$keyword` or an alias of it.
     *
     * @param array<string, mixed> $element
     */
    private function aliasKey(ActiveContext $context, array $element, string $keyword): ?string
    {
        if (array_key_exists($keyword, $element)) {
            return $keyword;
        }

        $alias = $context->aliasFor($keyword);

        return $alias !== null && array_key_exists($alias, $element) ? $alias : null;
    }

    /**
     * addValue from the specification: appends without duplicates.
     *
     * @param array<string, mixed> $object
     * @param mixed $value
     */
    private function addValue(array &$object, string $key, mixed $value, bool $asArray): void
    {
        if ($asArray) {
            if (!array_key_exists($key, $object) || !is_array($object[$key]) || !array_is_list($object[$key])) {
                $object[$key] = [];
            }

            foreach (is_array($value) && array_is_list($value) ? $value : [$value] as $item) {
                $object[$key][] = $item;
            }

            return;
        }

        if (!array_key_exists($key, $object)) {
            $object[$key] = $value;

            return;
        }

        $existing = $object[$key];

        if (is_array($existing) && array_is_list($existing)) {
            $existing[] = $value;
            $object[$key] = $existing;

            return;
        }

        $object[$key] = $existing === $value ? [$existing] : [$existing, $value];
    }

    private function load(string $input, ExpandOptions $options): LoadedContext
    {
        return $options->loader->load($input);
    }
}
