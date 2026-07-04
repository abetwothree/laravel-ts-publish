<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Cache;

use ReflectionClass;

class DependencyRecorder
{
    /** @var list<string> */
    protected static array $paths = [];

    protected static bool $recording = false;

    /**
     * Begin recording dependency file paths, clearing any previous capture.
     */
    public static function start(): void
    {
        static::$recording = true;
        static::$paths = [];
    }

    /**
     * Stop recording dependency file paths.
     */
    public static function stop(): void
    {
        static::$recording = false;
    }

    /**
     * Clear recorded paths without changing the recording state.
     */
    public static function reset(): void
    {
        static::$paths = [];
    }

    /**
     * Record a single dependency file path while recording is active.
     */
    public static function record(string $path): void
    {
        if (static::$recording && $path !== '') {
            static::$paths[] = $path;
        }
    }

    /**
     * Record a class's own file plus every parent class, trait (recursively),
     * and interface file. This makes a class's fingerprint change whenever any
     * of its ancestry changes, without per-transformer wiring.
     *
     * Guards with class_exists() so an unresolvable class string can never crash
     * generation — this is a cache side-channel and must stay silent on failure.
     * The guard also narrows the string to a class-string for reflection.
     */
    public static function recordClass(string $class): void
    {
        if (! static::$recording || ! class_exists($class)) {
            return;
        }

        $reflection = new ReflectionClass($class);

        static::recordReflection($reflection);

        foreach ($reflection->getInterfaceNames() as $interface) {
            static::recordFileFor($interface);
        }

        $parent = $reflection->getParentClass();

        while ($parent !== false) {
            static::recordReflection($parent);
            $parent = $parent->getParentClass();
        }
    }

    /**
     * The de-duplicated list of dependency paths captured since start().
     *
     * @return list<string>
     */
    public static function paths(): array
    {
        return array_values(array_unique(static::$paths));
    }

    /**
     * Record a reflection's own file plus, recursively, the files of every
     * trait it uses (and traits used by those traits — getTraits() returns only
     * direct traits, so recursion is required for nested trait chains).
     *
     * @param  ReflectionClass<object>  $reflection
     */
    protected static function recordReflection(ReflectionClass $reflection): void
    {
        $file = $reflection->getFileName();

        if (is_string($file)) {
            static::$paths[] = $file;
        }

        foreach ($reflection->getTraits() as $trait) {
            static::recordReflection($trait);
        }
    }

    /**
     * Record the source file for an interface (or class) name. Names passed here
     * come from reflection of an already-loaded class, so they always resolve.
     *
     * @param  class-string  $class
     */
    protected static function recordFileFor(string $class): void
    {
        $file = (new ReflectionClass($class))->getFileName();

        if (is_string($file)) {
            static::$paths[] = $file;
        }
    }
}
