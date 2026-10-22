<?php

declare(strict_types=1);

namespace Rasuvaeff\JsonLd;

/**
 * JSON-LD 1.1 keywords.
 *
 * @internal
 */
final class Keywords
{
    public const string CONTEXT = '@context';

    public const string ID = '@id';

    public const string VALUE = '@value';

    public const string TYPE = '@type';

    public const string GRAPH = '@graph';

    public const string LIST = '@list';

    public const string SET = '@set';

    public const string REVERSE = '@reverse';

    public const string INDEX = '@index';

    public const string LANGUAGE = '@language';

    public const string DIRECTION = '@direction';

    public const string NEST = '@nest';

    public const string INCLUDED = '@included';

    public const string NONE = '@none';

    public const string JSON = '@json';

    public const string VOCAB = '@vocab';

    public const string BASE = '@base';

    public const string VERSION = '@version';

    public const string IMPORT = '@import';

    public const string PROPAGATE = '@propagate';

    public const string PROTECTED = '@protected';

    public const string PREFIX = '@prefix';

    /** Context-level keywords, never valid as a term. */
    public const array CONTEXT_LEVEL = [
        self::CONTEXT,
        self::BASE,
        self::VOCAB,
        self::LANGUAGE,
        self::DIRECTION,
        self::VERSION,
        self::IMPORT,
        self::PROPAGATE,
        self::PROTECTED,
    ];

    /** @var list<string>|null */
    private static ?array $all = null;

    public static function isKeyword(mixed $value): bool
    {
        return is_string($value) && in_array($value, self::all(), true);
    }

    /** @return list<string> */
    public static function all(): array
    {
        return self::$all ??= [
            self::CONTEXT,
            self::ID,
            self::VALUE,
            self::TYPE,
            self::GRAPH,
            self::LIST,
            self::SET,
            self::REVERSE,
            self::INDEX,
            self::LANGUAGE,
            self::DIRECTION,
            self::NEST,
            self::INCLUDED,
            self::NONE,
            self::JSON,
        ];
    }
}
