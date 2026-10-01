<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Cache;

/**
 * The set of model classes this run emits a .ts file for: the collected ones, and each model their relations reach.
 * Empty means "no information", as in a run that skips the model phase, so callers must fail open.
 * A class name matches with or without the leading backslash a fully qualified spelling carries.
 */
class PublishedModelRegistry
{
    /** @var array<class-string, true> */
    protected static array $published = [];

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
            static::$published[ltrim($fqcn, '\\')] = true;
        }

        static::$version++;
        static::$signature = null;
    }

    /**
     * Drop every registered class, returning the registry to its no-information state.
     */
    public static function reset(): void
    {
        static::$published = [];
        static::$version++;
        static::$signature = null;
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
        return static::$published === [] || isset(static::$published[ltrim($fqcn, '\\')]);
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

        return static::$signature = $fqcns === [] ? '' : hash('xxh128', implode("\n", $fqcns));
    }
}
