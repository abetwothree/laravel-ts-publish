<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Collectors;

use Illuminate\Support\Facades\Config;

/**
 * The enums `ts:publish` writes a file for, as the configured enum collector lists them, collected once per process
 * for each `ts-publish.enums` config.
 */
final class PublishedEnums
{
    /** The fingerprint of the `ts-publish.enums` config the set was collected under; null before the first read. */
    private static ?string $configKey = null;

    /** @var array<string, true> */
    private static array $enums = [];

    /**
     * Whether `ts:publish` writes the enum's file: enum publishing is on and the configured collector lists the enum.
     */
    public static function includes(string $enum): bool
    {
        $key = hash('xxh128', serialize(Config::array('ts-publish.enums')));

        if ($key !== self::$configKey) {
            self::$enums = Config::boolean('ts-publish.enums.enabled') ? self::collect() : [];
            self::$configKey = $key;
        }

        return isset(self::$enums[$enum]);
    }

    /**
     * Forget the collected set, so the next read collects again.
     */
    public static function flush(): void
    {
        self::$configKey = null;
        self::$enums = [];
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
