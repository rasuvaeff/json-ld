<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd;

use InvalidArgumentException;
use Rasuvaeff\JsonLd\Loader\ContextLoaderInterface;
use Rasuvaeff\JsonLd\Loader\InlineContextLoader;

/**
 * Options shared by every top-level operation: expansion, flattening, the
 * node API and RDF conversion.
 *
 * @api
 */
final readonly class ProcessingOptions
{
    public const int DEFAULT_MAX_DEPTH = 512;

    /** Deepest nesting json_decode() can be asked for. */
    public const int MAX_DEPTH_LIMIT = 2_147_483_646;

    /**
     * Maximum nesting depth of arrays and objects, both when an inline JSON
     * string is decoded and during expansion.
     *
     * @var int<1, 2147483646>
     */
    public int $maxDepth;

    /**
     * Maximum length of an inline JSON string input in bytes; null means no
     * limit. Decoded arrays are not measured.
     *
     * @var int<1, max>|null
     */
    public ?int $maxBytes;

    /**
     * @param string|null $base base IRI used to resolve relative references before the document provides its own `@base`
     * @param string|null $documentUrl location of the document itself: relative context references resolve against it
     *                                 even when `base` redirects IRI resolution elsewhere
     * @param mixed $expandContext context processed before the document itself, as decoded JSON or an inline JSON string
     * @param ContextLoaderInterface $loader loader for context references; the default never performs I/O
     * @param int $maxDepth see {@see self::$maxDepth}
     * @param int|null $maxBytes see {@see self::$maxBytes}
     *
     * @throws InvalidArgumentException when a limit is out of range
     */
    public function __construct(
        public ?string $base = null,
        public ?string $documentUrl = null,
        public mixed $expandContext = null,
        public ContextLoaderInterface $loader = new InlineContextLoader(),
        int $maxDepth = self::DEFAULT_MAX_DEPTH,
        ?int $maxBytes = null,
    ) {
        if ($maxDepth < 1 || $maxDepth > self::MAX_DEPTH_LIMIT) {
            throw new InvalidArgumentException(
                sprintf('Maximum depth must be between 1 and %d, %d given', self::MAX_DEPTH_LIMIT, $maxDepth),
            );
        }

        if ($maxBytes !== null && $maxBytes < 1) {
            throw new InvalidArgumentException(sprintf('Maximum size must be positive, %d given', $maxBytes));
        }

        $this->maxDepth = $maxDepth;
        $this->maxBytes = $maxBytes;
    }
}
