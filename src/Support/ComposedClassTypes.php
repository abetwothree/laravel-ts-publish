<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

/**
 * The classes whose override is composed from two or more, and the name and marker a value of one publishes with.
 *
 * A value carries a composed type as a custom import keyed by a marker, so every import channel moves it unchanged
 * and only the code that writes an import line has to read the marker as the composed class's module.
 *
 * @internal
 */
final class ComposedClassTypes
{
    public const string MARKER_PREFIX = 'ts-publish:class-type:';

    /** @var array<class-string, true> */
    private static array $registered = [];

    /**
     * The interface name a composed class publishes as: its basename, suffixed `Type`.
     */
    public static function typeName(string $fqcn): string
    {
        return class_basename(ltrim($fqcn, '\\')).'Type';
    }

    /**
     * The custom-import key that stands for a composed class's module, which no module specifier can spell.
     */
    public static function marker(string $fqcn): string
    {
        return self::MARKER_PREFIX.ltrim($fqcn, '\\');
    }

    /**
     * Whether a custom-import key is a composed class's marker rather than a module specifier.
     */
    public static function isMarker(string $path): bool
    {
        return str_starts_with($path, self::MARKER_PREFIX);
    }

    /**
     * The class a marker stands for, or null for a module specifier.
     */
    public static function classOf(string $marker): ?string
    {
        return self::isMarker($marker) ? substr($marker, strlen(self::MARKER_PREFIX)) : null;
    }

    /**
     * The composed classes the marker keys of these import maps stand for, in first-seen order.
     *
     * @param  array<string, list<string>>  ...$importMaps
     * @return list<class-string>
     */
    public static function classesIn(array ...$importMaps): array
    {
        $classes = [];

        foreach ($importMaps as $imports) {
            foreach (array_keys($imports) as $path) {
                $class = self::classOf((string) $path);

                if ($class !== null) {
                    $classes[$class] = true;
                }
            }
        }

        /** @var list<class-string> */
        return array_keys($classes);
    }

    /**
     * Record a composed class a published value reached, by its declared name, so its module is written.
     *
     * @param  class-string  $fqcn
     */
    public static function register(string $fqcn): void
    {
        self::$registered[$fqcn] = true;
    }

    /**
     * Every composed class registered this run, in the order values first reached them.
     *
     * @return list<class-string>
     */
    public static function registered(): array
    {
        return array_keys(self::$registered);
    }

    /**
     * Forget every registered class, at the start of a run.
     */
    public static function flush(): void
    {
        self::$registered = [];
    }
}
