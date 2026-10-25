<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Collectors;

use Illuminate\Support\Facades\Config;

/**
 * The enums `ts:publish` writes a file for, as the configured enum collector lists them, collected once per process
 * for each `ts-publish.enums` config.
 *
 * @internal
 */
final class PublishedEnums
{
    /** @var array{mixed}|null the `ts-publish.enums` config the set is collected under, wrapped; null before a read */
    private static ?array $config = null;

    /** Bumped each time a read finds that config changed, or the set flushed, since the read before. */
    private static int $version = 0;

    /** @var array<string, true>|null the enums collected under that config; null until a read collects them */
    private static ?array $enums = null;

    /**
     * Whether `ts:publish` writes the enum's file: enum publishing is on and the configured collector lists the enum.
     */
    public static function includes(string $enum): bool
    {
        self::version();
        self::$enums ??= Config::boolean('ts-publish.enums.enabled') ? self::collect() : [];

        return isset(self::$enums[$enum]);
    }

    /**
     * A number that changes whenever the `ts-publish.enums` config the set is collected under does, so an answer
     * worked out under one set is never reused under another.
     */
    public static function version(): int
    {
        $config = [Config::get('ts-publish.enums')];

        if ($config !== self::$config) {
            self::$config = $config;
            self::$enums = null;
            self::$version++;
        }

        return self::$version;
    }

    /**
     * Forget the collected set, so the next read collects again.
     */
    public static function flush(): void
    {
        self::$config = null;
        self::$enums = null;
    }

    /**
     * The enums the configured collector lists, keyed by class name.
     *
     * @return array<string, true>
     */
    private static function collect(): array
    {
        /** @var EnumsCollector $collector */
        $collector = resolve(Config::string('ts-publish.enums.collector_class', EnumsCollector::class));

        return array_fill_keys($collector->collect()->all(), true);
    }
}
