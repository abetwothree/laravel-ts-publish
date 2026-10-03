<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Cache;

/**
 * The set of model classes this run emits a .ts file for: the collected ones, and each model their relations reach.
 * Until a run registers its set the registry holds no information and callers fail open; an empty set narrows to none.
 * A class name matches in any letter case, as PHP resolves one, and with or without a leading backslash.
 */
class PublishedModelRegistry
{
    /** @var array<string, true> */
    protected static array $published = [];

    /** Whether a run registered its set, so an empty one means "nothing is published", not "no information". */
    protected static bool $registered = false;

    /** Bumped on every change, so an analysis read against an older set is never reused. */
    protected static int $version = 0;

    /** The set's hash, worked out once per version. */
    protected static ?string $signature = null;

    /**
     * Add the model classes this run will emit to the published set.
     *
     * @param  iterable<class-string>  $fqcns
     */
    public static function register(iterable $fqcns): void
    {
        foreach ($fqcns as $fqcn) {
            static::$published[static::normalize($fqcn)] = true;
        }

        static::$registered = true;
        static::$version++;
        static::$signature = null;
    }

    /**
     * Drop every registered class, returning the registry to its no-information state.
     */
    public static function reset(): void
    {
        static::$published = [];
        static::$registered = false;
        static::$version++;
        static::$signature = null;
    }

    /**
     * Whether the registry holds no information, so callers cannot narrow anything.
     */
    public static function isEmpty(): bool
    {
        return ! static::$registered;
    }

    /**
     * Whether this run emits the class — true for every class until a set is registered.
     */
    public static function isPublished(string $fqcn): bool
    {
        return ! static::$registered || isset(static::$published[static::normalize($fqcn)]);
    }

    /**
     * A number that changes whenever the set does.
     */
    public static function version(): int
    {
        return static::$version;
    }

    /**
     * A hash of the set for the generation cache: a class joining or leaving it changes what its neighbors may name.
     */
    public static function signature(): string
    {
        if (static::$signature !== null) {
            return static::$signature;
        }

        $fqcns = array_keys(static::$published);
        sort($fqcns);

        return static::$signature = static::$registered ? hash('xxh128', implode("\n", $fqcns)) : '';
    }

    /**
     * The key for a class name: lower case, as PHP resolves class names in any case, with no leading backslash.
     */
    protected static function normalize(string $fqcn): string
    {
        return strtolower(ltrim($fqcn, '\\'));
    }
}
