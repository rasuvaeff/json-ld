<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd;

use Rasuvaeff\JsonLd\Context\ActiveContext;
use Rasuvaeff\JsonLd\Context\ContextProcessor;
use Rasuvaeff\JsonLd\Exception\JsonLdException;

/**
 * Expansion, JSON-LD 1.1 API section 5.1: turns compact documents into the
 * expanded form.
 *
 * Every thrown exception message equals the corresponding JSON-LD error
 * code from the W3C test suite.
 *
 * @api
 */
final class Expander
{
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
        /** @var list<mixed> */
        return ExpansionRun::strip($this->expandRaw($input, $options));
    }

    /**
     * Like {@see self::expand()}, but empty node objects stay marked so that
     * the node map can still turn them into blank nodes.
     *
     * @param string|array<mixed> $input
     *
     * @return list<mixed>
     *
     * @internal
     */
    public function expandRaw(string|array $input, ?ExpandOptions $options = null): array
    {
        $options ??= new ExpandOptions();
        $documentUrl = $options->documentUrl;

        if (is_string($input)) {
            $loaded = $options->loader->load($input);
            $input = $loaded->document;

            if ($loaded->documentUrl !== '' && $documentUrl === null) {
                $documentUrl = $loaded->documentUrl;
            }
        }

        $base = $options->base ?? $documentUrl;
        $baseUrl = $documentUrl ?? $base;

        $processor = new ContextProcessor($options->loader);
        $context = new ActiveContext(base: $base, originalBase: $base);

        if ($options->expandContext !== null) {
            $expandContext = $options->expandContext;

            if (is_array($expandContext) && !array_is_list($expandContext) && array_key_exists(Keywords::CONTEXT, $expandContext)) {
                $expandContext = $expandContext[Keywords::CONTEXT];
            }

            $context = $processor->process($context, $expandContext, $baseUrl);
        }

        $expanded = (new ExpansionRun($processor, $baseUrl))->expand($context, null, $input);
        if (is_array($expanded) && !array_is_list($expanded)
            && count($expanded) === 1
            && array_key_exists(Keywords::GRAPH, $expanded)
        ) {
            $expanded = $expanded[Keywords::GRAPH];
        }

        if ($expanded === null) {
            return [];
        }

        return is_array($expanded) && array_is_list($expanded) ? $expanded : [$expanded];
    }
}
