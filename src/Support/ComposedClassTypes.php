<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

use AbeTwoThree\LaravelTsPublish\Collectors\ClassTypesCollector;
use AbeTwoThree\LaravelTsPublish\Collectors\CoreCollector;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use Illuminate\Support\Facades\Config;

/**
 * The classes whose override is composed from two or more, and the name and marker a value of one publishes with.
 *
 * A value carries a composed type as a custom import keyed by a marker, so every import channel moves it unchanged
 * and only the code that writes an import line has to read the marker as the composed class's module.
 *
 * @phpstan-import-type TypesImportMap from Datable
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
     * Each composed class the class-types collector scans, with the classes, traits and interfaces declaring the
     * overrides it composes: the watcher lists their files, and publishing one by source republishes the module.
     *
     * @return array<class-string, list<class-string>>
     */
    public static function scanned(): array
    {
        if (! self::enabled()) {
            return [];
        }

        /** @var ClassTypesCollector $collector */
        $collector = resolve(Config::string('ts-publish.class_types.collector_class', ClassTypesCollector::class));

        $scanned = [];

        foreach ($collector->collect() as $class) {
            $scanned[$class] = array_column(ClassTypeOverride::resolve($class)['contributors'] ?? [], 'declaredBy');
        }

        return $scanned;
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
     * Merge an import map into `$into`, each marker key resolved through importPath() and its names joining those the
     * path already has; key order is kept, so a caller sorts after. Only the code writing import lines calls this: a
     * stored map keeps its markers, which composedClassTypes() reads back from a cached snapshot.
     *
     * @param  TypesImportMap  $imports
     * @param  TypesImportMap  $into
     * @return TypesImportMap
     */
    public static function resolveImports(array $imports, string $fromNamespacePath, array $into = []): array
    {
        foreach ($imports as $path => $names) {
            if ($names !== []) {
                $path = self::importPath((string) $path, $fromNamespacePath);
                $into[$path] = array_values(array_unique([...($into[$path] ?? []), ...$names]));
            }
        }

        return $into;
    }

    /**
     * Each class's composed type name, mapped to that name qualified by the class's namespace in the globals file.
     * The first of two classes sharing a name keeps it, as warnOfSharedNames() says.
     *
     * @param  list<class-string>  $classes
     * @return array<string, string>
     */
    public static function globalReferences(array $classes): array
    {
        $references = [];

        foreach ($classes as $class) {
            $references[self::typeName($class)] ??= TsNaming::globalNamespace($class).'.'.self::typeName($class);
        }

        return $references;
    }

    /**
     * Warn when one file reaches two composed classes that publish one type name. A composed name is a custom import,
     * which no registry aliases, so the file imports it twice and the globals file reads both as the first.
     *
     * @param  list<class-string>  $classes  the classes, in the order the file reaches them
     */
    public static function warnOfSharedNames(string $subject, array $classes): void
    {
        $byName = [];

        foreach ($classes as $class) {
            $byName[self::typeName($class)][] = $class;
        }

        foreach ($byName as $name => $named) {
            if (count($named) > 1) {
                AnalysisWarnings::addOnce($subject, sprintf(
                    'Imports [%s] from composed classes [%s], so its file imports that name twice (TS2300), and its globals entry, if any, reads them all as [%s]. Give all but one a #[TsType] of its own with inherit: false.',
                    $name,
                    implode('] and [', $named),
                    $named[0],
                ));
            }
        }
    }

    /**
     * The composed classes the marker keys of these import maps stand for, in first-seen order.
     *
     * @param  TypesImportMap  ...$importMaps
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
