<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd;

use Rasuvaeff\JsonLd\Context\ActiveContext;
use Rasuvaeff\JsonLd\Context\ContextProcessor;
use Rasuvaeff\JsonLd\Context\Syntax;
use Rasuvaeff\JsonLd\Context\TermDefinition;
use Rasuvaeff\JsonLd\Exception\InvalidInputException;

/**
 * The recursive Expansion Algorithm, JSON-LD 1.1 API section 5.1.2, for one
 * document.
 *
 * Results are `null` (dropped), a list, or a map. A node object that
 * expanded to nothing is the {@see self::EMPTY} marker so that it stays
 * distinguishable from an empty list until {@see self::strip()}.
 *
 * @internal
 *
 * @psalm-import-type JsonValue from \Rasuvaeff\JsonLd\Json
 */
final readonly class ExpansionRun
{
    public const string EMPTY = "\0empty";

    public function __construct(
        private ContextProcessor $processor,
        private ?string $baseUrl,
    ) {}

    public static function strip(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_key_exists(self::EMPTY, $value)) {
            return [];
        }

        return array_map(self::strip(...), $value);
    }

    /**
     * @return mixed null when dropped, a list, or a map
     */
    public function expand(ActiveContext $context, ?string $activeProperty, mixed $element, bool $fromMap = false): mixed
    {
        if ($element === null) {
            return null;
        }

        $definition = $activeProperty !== null ? $context->term($activeProperty) : null;
        $propertyScoped = $definition !== null && $definition->hasContext;

        if (!is_array($element)) {
            if ($activeProperty === null || $activeProperty === Keywords::GRAPH) {
                return null;
            }

            if ($propertyScoped) {
                $context = $this->processor->process(
                    $context,
                    $definition->context,
                    $definition->baseUrl,
                    overrideProtected: true,
                );
            }

            return $this->expandValue($context, $activeProperty, $element);
        }

        if (array_is_list($element)) {
            $result = [];
            $listContainer = $definition !== null && in_array(Keywords::LIST, $definition->container, true);

            /** @var JsonValue $item */
            foreach ($element as $item) {
                /** @var JsonValue $expanded */
                $expanded = $this->expand($context, $activeProperty, $item, $fromMap);

                if ($listContainer && is_array($expanded) && array_is_list($expanded)) {
                    $expanded = [Keywords::LIST => $expanded];
                }

                if ($expanded === null) {
                    continue;
                }

                if (is_array($expanded) && array_is_list($expanded)) {
                    array_push($result, ...$expanded);
                } else {
                    $result[] = $expanded;
                }
            }

            return $result;
        }

        return $this->expandObject($context, $activeProperty, $element, $definition, $fromMap);
    }

    /**
     * @param array<array-key, mixed> $element
     */
    private function expandObject(
        ActiveContext $context,
        ?string $activeProperty,
        array $element,
        ?TermDefinition $propertyDefinition,
        bool $fromMap,
    ): mixed {
        if ($context->previous !== null && !$fromMap) {
            $hasValue = false;

            foreach (array_keys($element) as $key) {
                if ($this->keywordOf($context, strval($key)) === Keywords::VALUE) {
                    $hasValue = true;
                }
            }

            $onlyId = count($element) === 1 && $this->keywordOf($context, strval(array_key_first($element))) === Keywords::ID;

            if (!$hasValue && !$onlyId) {
                $context = $context->previous;
            }
        }

        if ($propertyDefinition !== null && $propertyDefinition->hasContext) {
            $context = $this->processor->process(
                $context,
                $propertyDefinition->context,
                $propertyDefinition->baseUrl,
                overrideProtected: true,
            );
        }

        if (array_key_exists(Keywords::CONTEXT, $element)) {
            $context = $this->processor->process($context, $element[Keywords::CONTEXT], $this->baseUrl);
        }

        $typeScoped = $context;
        $keys = array_map(strval(...), array_keys($element));
        sort($keys);

        foreach ($keys as $key) {
            if ($this->keywordOf($typeScoped, $key) !== Keywords::TYPE) {
                continue;
            }

            /** @var list<string> $terms */
            $terms = array_values(array_filter(self::asList($element[$key]), is_string(...)));
            sort($terms);

            foreach ($terms as $term) {
                $definition = $typeScoped->term($term);

                if ($definition !== null && $definition->hasContext) {
                    $context = $this->processor->process(
                        $context,
                        $definition->context,
                        $definition->baseUrl,
                        propagate: false,
                    );
                }
            }
        }

        $inputType = null;

        foreach ($keys as $key) {
            if ($this->keywordOf($context, $key) === Keywords::TYPE) {
                $types = self::asList($element[$key]);
                /** @var JsonValue $last */
                $last = $types === [] ? null : $types[array_key_last($types)];

                if (is_string($last)) {
                    $inputType = $this->processor->expandIri($context, $last, vocab: true);
                }

                break;
            }
        }

        $result = [];
        $this->expandEntries($context, $activeProperty, $element, $result, $inputType, $typeScoped);

        if (array_key_exists(Keywords::VALUE, $result)) {
            foreach (array_keys($result) as $key) {
                if (!in_array($key, [Keywords::DIRECTION, Keywords::INDEX, Keywords::LANGUAGE, Keywords::TYPE, Keywords::VALUE], true)) {
                    throw new InvalidInputException('invalid value object');
                }
            }

            if (array_key_exists(Keywords::TYPE, $result)
                && (array_key_exists(Keywords::LANGUAGE, $result) || array_key_exists(Keywords::DIRECTION, $result))
            ) {
                throw new InvalidInputException('invalid value object');
            }

            /** @var JsonValue $types */
            $types = $result[Keywords::TYPE] ?? null;

            if ($types === [Keywords::JSON]) {
                $result[Keywords::TYPE] = Keywords::JSON;
            } elseif ($result[Keywords::VALUE] === null) {
                return null;
            } elseif (!is_string($result[Keywords::VALUE]) && array_key_exists(Keywords::LANGUAGE, $result)) {
                throw new InvalidInputException('invalid language-tagged value');
            } elseif ($types !== null) {
                if (!is_array($types) || count($types) !== 1
                    || !is_string($types[0]) || !Syntax::isAbsoluteIri($types[0])
                ) {
                    throw new InvalidInputException('invalid typed value');
                }

                $result[Keywords::TYPE] = $types[0];
            }
        } elseif (array_key_exists(Keywords::SET, $result) || array_key_exists(Keywords::LIST, $result)) {
            foreach (array_keys($result) as $key) {
                if (!in_array($key, [Keywords::SET, Keywords::LIST, Keywords::INDEX], true)) {
                    throw new InvalidInputException('invalid set or list object');
                }
            }

            if (array_key_exists(Keywords::SET, $result)) {
                return self::asList($result[Keywords::SET]);
            }
        }

        if (array_keys($result) === [Keywords::LANGUAGE]) {
            return null;
        }

        if ($activeProperty === null || $activeProperty === Keywords::GRAPH) {
            if ($result === []
                || array_key_exists(Keywords::VALUE, $result)
                || array_key_exists(Keywords::LIST, $result)
                || array_keys($result) === [Keywords::ID]
            ) {
                return null;
            }
        }

        return $result === [] ? [self::EMPTY => true] : $result;
    }

    /**
     * Steps 13 and 14 of the algorithm: the members of one (possibly nested)
     * map.
     *
     * @param array<array-key, mixed> $element
     * @param array<string, mixed> $result
     */
    private function expandEntries(
        ActiveContext $context,
        ?string $activeProperty,
        array $element,
        array &$result,
        ?string $inputType,
        ActiveContext $typeScoped,
    ): void {
        $nests = [];

        /** @var JsonValue $value */
        foreach ($element as $key => $value) {
            $key = strval($key);

            if ($key === Keywords::CONTEXT) {
                continue;
            }

            $expandedProperty = $this->processor->expandIri($context, $key, vocab: true);

            if ($expandedProperty === null
                || (!str_contains($expandedProperty, ':') && !Keywords::isKeyword($expandedProperty))
            ) {
                continue;
            }

            if (Keywords::isKeyword($expandedProperty)) {
                if ($activeProperty === Keywords::REVERSE) {
                    throw new InvalidInputException('invalid reverse property map');
                }

                if (array_key_exists($expandedProperty, $result)
                    && $expandedProperty !== Keywords::INCLUDED
                    && $expandedProperty !== Keywords::TYPE
                ) {
                    throw new InvalidInputException('colliding keywords');
                }

                if ($expandedProperty === Keywords::NEST) {
                    $nests[] = $key;

                    continue;
                }

                $this->expandKeywordEntry($context, $activeProperty, $expandedProperty, $value, $result, $inputType, $typeScoped);

                continue;
            }

            $definition = $context->term($key);
            $container = $definition->container ?? [];

            if ($definition !== null && $definition->type === Keywords::JSON) {
                $expandedValue = [Keywords::VALUE => $value, Keywords::TYPE => Keywords::JSON];
            } elseif (in_array(Keywords::LANGUAGE, $container, true) && self::isMap($value)) {
                $expandedValue = $this->expandLanguageMap($context, $definition, $value);
            } elseif (self::isMap($value)
                && array_intersect($container, [Keywords::INDEX, Keywords::TYPE, Keywords::ID]) !== []
            ) {
                $expandedValue = $this->expandIndexMap($context, $key, $definition, $container, $value);
            } else {
                /** @var JsonValue $expandedValue */
                $expandedValue = $this->expand($context, $key, $value);
            }

            if ($expandedValue === null) {
                continue;
            }

            if (in_array(Keywords::LIST, $container, true) && !self::isListObject($expandedValue)) {
                $expandedValue = [Keywords::LIST => self::asList($expandedValue)];
            }

            if (in_array(Keywords::GRAPH, $container, true)
                && !in_array(Keywords::ID, $container, true)
                && !in_array(Keywords::INDEX, $container, true)
            ) {
                $expandedValue = array_map(
                    static fn(mixed $item): array => [Keywords::GRAPH => self::asList($item)],
                    self::asList($expandedValue),
                );
            }

            if ($definition !== null && $definition->reverse) {
                /** @var array<string, mixed> $reverse */
                $reverse = $result[Keywords::REVERSE] ?? [];

                /** @var JsonValue $item */
                foreach (self::asList($expandedValue) as $item) {
                    if (self::isValueObject($item) || self::isListObject($item)) {
                        throw new InvalidInputException('invalid reverse property value');
                    }
                }

                self::append($reverse, $expandedProperty, $expandedValue);
                $result[Keywords::REVERSE] = $reverse;

                continue;
            }

            self::append($result, $expandedProperty, $expandedValue);
        }

        foreach ($nests as $nestKey) {
            $nestContext = $context;
            $nestDefinition = $context->term($nestKey);

            if ($nestDefinition !== null && $nestDefinition->hasContext) {
                $nestContext = $this->processor->process(
                    $context,
                    $nestDefinition->context,
                    $nestDefinition->baseUrl,
                    overrideProtected: true,
                );
            }

            /** @var JsonValue $nested */
            foreach (self::asList($element[$nestKey]) as $nested) {
                if (!self::isMap($nested)) {
                    throw new InvalidInputException('invalid @nest value');
                }

                foreach (array_keys($nested) as $nestedKey) {
                    if ($this->keywordOf($context, strval($nestedKey)) === Keywords::VALUE) {
                        throw new InvalidInputException('invalid @nest value');
                    }
                }

                $this->expandEntries($nestContext, $activeProperty, $nested, $result, $inputType, $typeScoped);
            }
        }
    }

    /**
     * @param JsonValue $value
     * @param array<string, mixed> $result
     */
    private function expandKeywordEntry(
        ActiveContext $context,
        ?string $activeProperty,
        string $keyword,
        mixed $value,
        array &$result,
        ?string $inputType,
        ActiveContext $typeScoped,
    ): void {
        switch ($keyword) {
            case Keywords::ID:
                if (!is_string($value)) {
                    throw new InvalidInputException('invalid @id value');
                }

                $result[Keywords::ID] = $this->processor->expandIri($context, $value, documentRelative: true);

                return;

            case Keywords::TYPE:
                $types = [];

                foreach (self::asList($value) as $type) {
                    if (!is_string($type)) {
                        throw new InvalidInputException('invalid type value');
                    }

                    $expanded = $this->processor->expandIri($typeScoped, $type, vocab: true, documentRelative: true);

                    if ($expanded === null) {
                        throw new InvalidInputException('invalid type value');
                    }

                    $types[] = $expanded;
                }

                if (!is_string($value) && !(is_array($value) && array_is_list($value))) {
                    throw new InvalidInputException('invalid type value');
                }

                /** @var list<mixed> $knownTypes */
                $knownTypes = $result[Keywords::TYPE] ?? [];
                $result[Keywords::TYPE] = [...$knownTypes, ...$types];

                return;

            case Keywords::GRAPH:
                $result[Keywords::GRAPH] = self::asList($this->expand($context, Keywords::GRAPH, $value));

                return;

            case Keywords::INCLUDED:
                $included = self::asList($this->expand($context, Keywords::INCLUDED, $value));

                foreach ($included as $node) {
                    if (!self::isMap($node) || self::isValueObject($node) || self::isListObject($node)) {
                        throw new InvalidInputException('invalid @included value');
                    }
                }

                /** @var list<mixed> $knownIncluded */
                $knownIncluded = $result[Keywords::INCLUDED] ?? [];
                $result[Keywords::INCLUDED] = [...$knownIncluded, ...$included];

                return;

            case Keywords::VALUE:
                if ($inputType !== Keywords::JSON && is_array($value)) {
                    throw new InvalidInputException('invalid value object value');
                }

                $result[Keywords::VALUE] = $value;

                return;

            case Keywords::LANGUAGE:
                if (!is_string($value)) {
                    throw new InvalidInputException('invalid language-tagged string');
                }

                $result[Keywords::LANGUAGE] = $value;

                return;

            case Keywords::DIRECTION:
                if ($value !== 'ltr' && $value !== 'rtl') {
                    throw new InvalidInputException('invalid base direction');
                }

                $result[Keywords::DIRECTION] = $value;

                return;

            case Keywords::INDEX:
                if (!is_string($value)) {
                    throw new InvalidInputException('invalid @index value');
                }

                $result[Keywords::INDEX] = $value;

                return;

            case Keywords::LIST:
                if ($activeProperty === null || $activeProperty === Keywords::GRAPH) {
                    return;
                }

                $result[Keywords::LIST] = self::asList($this->expand($context, $activeProperty, $value));

                return;

            case Keywords::SET:
                /** @var JsonValue $expanded */
                $expanded = $this->expand($context, $activeProperty, $value);

                if ($expanded !== null) {
                    $result[Keywords::SET] = $expanded;
                }

                return;

            case Keywords::REVERSE:
                if (!self::isMap($value)) {
                    throw new InvalidInputException('invalid @reverse value');
                }

                /** @var JsonValue $expanded */
                $expanded = $this->expand($context, Keywords::REVERSE, $value);

                if (!self::isMap($expanded) || array_key_exists(self::EMPTY, $expanded)) {
                    return;
                }

                /** @var JsonValue $items */
                foreach ($expanded as $property => $items) {
                    if ($property === Keywords::REVERSE) {
                        foreach (self::asMap($items) as $reverseProperty => $reverseItems) {
                            self::append($result, strval($reverseProperty), $reverseItems);
                        }

                        continue;
                    }

                    /** @var JsonValue $item */
                    foreach (self::asList($items) as $item) {
                        if (self::isValueObject($item) || self::isListObject($item)) {
                            throw new InvalidInputException('invalid reverse property value');
                        }
                    }

                    /** @var array<string, mixed> $reverse */
                    $reverse = $result[Keywords::REVERSE] ?? [];
                    self::append($reverse, strval($property), $items);
                    $result[Keywords::REVERSE] = $reverse;
                }

                return;

            default:
                return;
        }
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return list<array<string, mixed>>
     */
    private function expandLanguageMap(ActiveContext $context, ?TermDefinition $definition, array $value): array
    {
        $direction = $definition !== null && $definition->hasDirection ? $definition->direction : $context->direction;
        $result = [];

        /** @var JsonValue $items */
        foreach ($value as $language => $items) {
            $language = strval($language);
            $none = $this->processor->expandIri($context, $language, vocab: true) === Keywords::NONE;

            /** @var JsonValue $item */
            foreach (self::asList($items) as $item) {
                if ($item === null) {
                    continue;
                }

                if (!is_string($item)) {
                    throw new InvalidInputException('invalid language map value');
                }

                $entry = [Keywords::VALUE => $item];

                if (!$none) {
                    $entry[Keywords::LANGUAGE] = $language;
                }

                if ($direction !== null) {
                    $entry[Keywords::DIRECTION] = $direction;
                }

                $result[] = $entry;
            }
        }

        return $result;
    }

    /**
     * @param list<string> $container
     * @param array<string, mixed> $value
     *
     * @return list<mixed>
     */
    private function expandIndexMap(
        ActiveContext $context,
        string $key,
        ?TermDefinition $definition,
        array $container,
        array $value,
    ): array {
        $result = [];
        $indexKey = $definition->index ?? Keywords::INDEX;

        /** @var JsonValue $indexValue */
        foreach ($value as $index => $indexValue) {
            $index = strval($index);
            $mapContext = $context;

            if (in_array(Keywords::TYPE, $container, true)) {
                $mapContext = $context->previous ?? $context;
                $indexDefinition = $mapContext->term($index);

                if ($indexDefinition !== null && $indexDefinition->hasContext) {
                    $mapContext = $this->processor->process(
                        $mapContext,
                        $indexDefinition->context,
                        $indexDefinition->baseUrl,
                    );
                }
            }

            $expandedIndex = $this->processor->expandIri($mapContext, $index, vocab: true);
            $items = self::asList($this->expand($mapContext, $key, self::asList($indexValue), true));

            /** @var JsonValue $item */
            foreach ($items as $item) {
                if (!self::isMap($item)) {
                    $result[] = $item;

                    continue;
                }

                unset($item[self::EMPTY]);

                if (in_array(Keywords::GRAPH, $container, true) && !self::isGraphObject($item)) {
                    $item = [Keywords::GRAPH => [$item]];
                }

                if (in_array(Keywords::INDEX, $container, true) && $indexKey !== Keywords::INDEX && $expandedIndex !== Keywords::NONE) {
                    $reexpanded = $this->expandValue($context, $indexKey, $index);
                    $expandedKey = $this->processor->expandIri($context, $indexKey, vocab: true);

                    if ($expandedKey !== null) {
                        $item[$expandedKey] = [$reexpanded, ...self::asList($item[$expandedKey] ?? [])];
                    }

                    if (self::isValueObject($item)) {
                        throw new InvalidInputException('invalid value object');
                    }
                } elseif (in_array(Keywords::INDEX, $container, true)
                    && !array_key_exists(Keywords::INDEX, $item)
                    && $expandedIndex !== Keywords::NONE
                ) {
                    $item[Keywords::INDEX] = $index;
                } elseif (in_array(Keywords::ID, $container, true)
                    && !array_key_exists(Keywords::ID, $item)
                    && $expandedIndex !== Keywords::NONE
                ) {
                    $item[Keywords::ID] = $this->processor->expandIri($mapContext, $index, documentRelative: true);
                } elseif (in_array(Keywords::TYPE, $container, true) && $expandedIndex !== Keywords::NONE) {
                    $item[Keywords::TYPE] = [$expandedIndex, ...self::asList($item[Keywords::TYPE] ?? [])];
                }

                $result[] = $item === [] ? [self::EMPTY => true] : $item;
            }
        }

        return $result;
    }

    /**
     * Value Expansion, API section 5.3.
     *
     * @return array<string, mixed>
     */
    private function expandValue(ActiveContext $context, string $activeProperty, mixed $value): array
    {
        $definition = $context->term($activeProperty);
        $type = $definition?->type;

        if ($type === Keywords::ID && is_string($value)) {
            return [Keywords::ID => $this->processor->expandIri($context, $value, documentRelative: true)];
        }

        if ($type === Keywords::VOCAB && is_string($value)) {
            return [Keywords::ID => $this->processor->expandIri($context, $value, vocab: true, documentRelative: true)];
        }

        $result = [Keywords::VALUE => $value];

        if ($type !== null && !in_array($type, [Keywords::ID, Keywords::VOCAB, Keywords::NONE], true)) {
            $result[Keywords::TYPE] = $type;
        } elseif (is_string($value)) {
            $language = $definition !== null && $definition->hasLanguage ? $definition->language : $context->language;
            $direction = $definition !== null && $definition->hasDirection ? $definition->direction : $context->direction;

            if ($language !== null) {
                $result[Keywords::LANGUAGE] = $language;
            }

            if ($direction !== null) {
                $result[Keywords::DIRECTION] = $direction;
            }
        }

        return $result;
    }

    private function keywordOf(ActiveContext $context, string $key): ?string
    {
        $expanded = $this->processor->expandIri($context, $key, vocab: true);

        return $expanded !== null && Keywords::isKeyword($expanded) ? $expanded : null;
    }

    /**
     * @return list<JsonValue>
     */
    private static function asList(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        /** @var list<JsonValue> $list */
        $list = is_array($value) && array_is_list($value) ? $value : [$value];

        return $list;
    }

    /**
     * @param array<string, mixed> $map
     * @param JsonValue $value
     */
    private static function append(array &$map, string $property, mixed $value): void
    {
        /** @var list<JsonValue> $values */
        $values = $map[$property] ?? [];

        foreach (self::asList($value) as $item) {
            $values[] = $item;
        }

        $map[$property] = $values;
    }

    /**
     * @return array<array-key, JsonValue>
     */
    private static function asMap(mixed $value): array
    {
        /** @var array<array-key, JsonValue> $map */
        $map = is_array($value) ? $value : [];

        return $map;
    }

    /**
     * @psalm-assert-if-true array<string, mixed> $value
     */
    private static function isMap(mixed $value): bool
    {
        return is_array($value) && !array_is_list($value);
    }

    private static function isValueObject(mixed $value): bool
    {
        return self::isMap($value) && array_key_exists(Keywords::VALUE, $value);
    }

    private static function isListObject(mixed $value): bool
    {
        return self::isMap($value) && array_key_exists(Keywords::LIST, $value);
    }

    private static function isGraphObject(mixed $value): bool
    {
        return self::isMap($value)
            && array_key_exists(Keywords::GRAPH, $value)
            && array_diff(array_keys($value), [Keywords::GRAPH, Keywords::ID, Keywords::INDEX]) === [];
    }
}
