<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Cache;

class OutputRecorder
{
    /** @var list<string> */
    protected static array $paths = [];

    protected static bool $recording = false;

    /**
     * Clear recorded paths without changing the recording state.
     */
    public static function reset(): void
    {
        static::$paths = [];
    }

    /**
     * Begin recording written output paths, clearing any previous capture.
     */
    public static function start(): void
    {
        static::$recording = true;
        static::$paths = [];
    }

    /**
     * Stop recording written output paths.
     */
    public static function stop(): void
    {
        static::$recording = false;
    }

    /**
     * Record a written output path while recording is active (no-op otherwise).
     */
    public static function record(string $path): void
    {
        if (static::$recording) {
            static::$paths[] = $path;
        }
    }

    /**
     * The de-duplicated list of output paths captured since start().
     *
     * @return list<string>
     */
    public static function paths(): array
    {
        return array_values(array_unique(static::$paths));
    }
}
