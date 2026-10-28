<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

use AbeTwoThree\LaravelTsPublish\Collectors\ClassTypesCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\CoreCollector;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use Illuminate\Support\Facades\Config;

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
     * Whether class types publish, a missing setting reading as the documented default.
     */
    public static function enabled(): bool
    {
        return Config::boolean('ts-publish.class_types.enabled', true);
    }

    /**
     * Whether a class may publish a composed type: class types are enabled, and the class-types collector's filters
     * and `#[TsExclude]` admit it. The collector and the composer both ask, so no value imports a module never written.
     *
     * @param  class-string  $class
     */
    public static function composes(string $class): bool
    {
        if (! self::enabled()) {
            return false;
        }

        $collector = resolve(Config::string('ts-publish.class_types.collector_class', ClassTypesCollector::class));

        // A project's own collector need only offer collect(), so only a CoreCollector can say what it filters out.
        return ! $collector instanceof CoreCollector || $collector->admits($class);
    }

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
     * The module a custom-import key names from a file in `$fromNamespacePath`: a marker becomes the relative path of
     * its class's namespace directory, whose barrel exports the module; any other key is a module specifier already.
     */
    public static function importPath(string $path, string $fromNamespacePath): string
    {
        $class = self::classOf($path);

        return $class === null ? $path : TsNaming::relativeImportPath($fromNamespacePath, TsNaming::namespaceToPath($class));
    }

    /**
     * An import map with each marker key resolved through importPath(), its names joining those its path already has.
     * The order of the other keys is kept, so a caller sorts after.
     *
     * @param  array<string, list<string>>  $imports
     * @return array<string, list<string>>
     */
    public static function resolveImports(array $imports, string $fromNamespacePath): array
    {
        $resolved = [];

        foreach ($imports as $path => $names) {
            $path = self::importPath((string) $path, $fromNamespacePath);
            $resolved[$path] = array_values(array_unique([...($resolved[$path] ?? []), ...$names]));
        }

        return $resolved;
    }

    /**
     * Each class's composed type name, mapped to that name qualified by the class's namespace in the globals file.
     *
     * @param  list<class-string>  $classes
     * @return array<string, string>
     */
    public static function globalReferences(array $classes): array
    {
        $references = [];

        foreach ($classes as $class) {
            $references[self::typeName($class)] = TsNaming::globalNamespace($class).'.'.self::typeName($class);
        }

        return $references;
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
