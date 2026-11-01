<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd;

use Rasuvaeff\JsonLd\Context\ActiveContext;
use Rasuvaeff\JsonLd\Context\ContextProcessor;
use Rasuvaeff\JsonLd\Exception\DocumentLoadingException;
use Rasuvaeff\JsonLd\Exception\ErrorCode;
use Rasuvaeff\JsonLd\Exception\JsonLdException;

/**
 * The expansion step every operation starts with: decodes or loads the
 * input, applies the options and runs the Expansion Algorithm. Empty node
 * objects stay marked ({@see ExpansionRun::EMPTY}) so that the node map can
 * still turn them into blank nodes; {@see Expander} strips the marks.
 *
 * @internal
 *
 * @psalm-import-type JsonValue from \Rasuvaeff\JsonLd\Json
 */
final class DocumentExpansion
{
    /**
     * @param string|array<mixed> $input
     *
     * @return list<mixed>
     *
     * @throws JsonLdException
     */
    public static function run(string|array $input, ?ProcessingOptions $options = null): array
    {
        $options ??= new ProcessingOptions();
        $documentUrl = $options->documentUrl;

        if (is_string($input)) {
            $trimmed = ltrim($input);

            if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
                $input = Json::decode($input, $options->maxDepth, $options->maxBytes);
            } else {
                try {
                    $loaded = $options->loader->load($input);
                } catch (\Throwable $exception) {
                    throw new DocumentLoadingException(
                        ErrorCode::LoadingDocumentFailed,
                        sprintf('loading document failed: "%s": %s', $input, $exception->getMessage()),
                        $exception,
                    );
                }

                $input = $loaded->document;

                if ($loaded->documentUrl !== '' && $documentUrl === null) {
                    $documentUrl = $loaded->documentUrl;
                }
            }
        }

        $base = $options->base ?? $documentUrl;
        $baseUrl = $documentUrl ?? $base;

        $processor = new ContextProcessor($options->loader);
        $context = new ActiveContext(base: $base, originalBase: $base);

        if ($options->expandContext !== null) {
            /** @var JsonValue $expandContext */
            $expandContext = $options->expandContext;

            if (is_array($expandContext) && !array_is_list($expandContext) && array_key_exists(Keywords::CONTEXT, $expandContext)) {
                /** @var JsonValue $expandContext */
                $expandContext = $expandContext[Keywords::CONTEXT];
            }

            $context = $processor->process($context, $expandContext, $baseUrl);
        }

        /** @var JsonValue $expanded */
        $expanded = (new ExpansionRun($processor, $baseUrl, $options->maxDepth))->expand($context, null, $input);
        if (is_array($expanded) && !array_is_list($expanded)
            && count($expanded) === 1
            && array_key_exists(Keywords::GRAPH, $expanded)
        ) {
            /** @var JsonValue $expanded */
            $expanded = $expanded[Keywords::GRAPH];
        }

        if ($expanded === null) {
            return [];
        }

        return is_array($expanded) && array_is_list($expanded) ? $expanded : [$expanded];
    }
}
