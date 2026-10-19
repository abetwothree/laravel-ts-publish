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
     * Each class's own, trait, interface and parent files, read once: a process cannot redeclare a class.
     *
     * @var array<class-string, list<string>>
     */
    protected static array $classFiles = [];

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
     * Record a class's or interface's own file plus every parent class, trait (recursively), and interface file.
     *
     * The existence checks guard reflection: this is a cache side-channel and must stay silent on a bad class name.
     * A repeat appends the files again, never skipped: a memo frame keeps only the paths recorded after its mark.
     */
    public static function recordClass(string $class): void
    {
        if (! static::$recording || (! class_exists($class) && ! interface_exists($class))) {
            return;
        }

        if (isset(static::$classFiles[$class])) {
            array_push(static::$paths, ...static::$classFiles[$class]);

            return;
        }

        $mark = count(static::$paths);
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

        static::$classFiles[$class] = array_slice(static::$paths, $mark);
    }

    /**
     * Whether dependency paths are being recorded.
     */
    public static function isRecording(): bool
    {
        return static::$recording;
    }

    /**
     * A position in the paths recorded since start(), for since() to read on from.
     */
    public static function mark(): int
    {
        return count(static::$paths);
    }

    /**
     * Every path recorded after a mark, in order and with repeats.
     *
     * @return list<string>
     */
    public static function since(int $mark): array
    {
        return array_slice(static::$paths, $mark);
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
     * Record a reflection's own file plus, recursively, the files of every trait it uses.
     *
     * ReflectionClass::getTraits() returns only direct traits, hence the recursion for nested trait chains.
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
     * Record the source file for an interface (or class) name.
     *
     * Unguarded: names reach here from reflection of an already-loaded class, so they always resolve.
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
