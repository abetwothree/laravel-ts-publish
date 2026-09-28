<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Cache;

/**
 * The set of resource classes this run will actually emit a .ts file for. Empty means "no
 * information" — single-class runs never populate it, so callers must fail open.
 */
class PublishedResourceRegistry
{
    /** @var array<class-string, true> */
    protected static array $published = [];

    /** Bumped on every change, so an analysis read against an older set is never reused. */
    protected static int $version = 0;

    /**
     * Add the resource classes this run will emit to the published set.
     *
     * @param  iterable<class-string>  $fqcns
     */
    public static function register(iterable $fqcns): void
    {
        foreach ($fqcns as $fqcn) {
            static::$published[$fqcn] = true;
        }

        static::$version++;
    }

    /**
     * Drop every registered class, returning the registry to its no-information state.
     */
    public static function reset(): void
    {
        static::$published = [];
        static::$version++;
    }

    /**
     * Whether the registry holds no information, so callers cannot narrow anything.
     */
    public static function isEmpty(): bool
    {
        return static::$published === [];
    }

    /**
     * Whether this run emits the class — true for every class while the registry is empty.
     */
    public static function isPublished(string $fqcn): bool
    {
        return static::$published === [] || isset(static::$published[$fqcn]);
    }

    /**
     * A number that changes whenever the set does.
     */
    public static function version(): int
    {
        return static::$version;
    }
}
