<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Inertia;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisImports;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\TsCastsReader;
use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Support\TsCastsImportResolver;
use Composer\ClassMapGenerator\ClassMapGenerator;
use ReflectionClass;

/**
 * @phpstan-import-type TsCastsUnpacked from TsCastsReader
 * @phpstan-import-type TypesImportMap from Datable
 *
 * @phpstan-type TsCastsParseResult = array{
 *     overrides: array<string, string>,
 *     importPaths: array<string, string>,
 * }
 * @phpstan-type SharedDataResult = array{
 *     sharedPageProps: string,
 *     withAllErrors: bool,
 *     typeImports: TypesImportMap,
 * }
 * @phpstan-type OverrideEntry = array{type: string, optional: bool}
 * @phpstan-type SharedPropMap = array<string, OverrideEntry>
 */
class InertiaSharedDataAnalyzer
{
    /**
     * Inertia sets `page.props.errors` itself and `@inertiajs/core` types it, so an inferred entry
     * for it can only weaken that; `errorValueType` is this package's channel for errors instead.
     */
    protected const FRAMEWORK_OWNED_PROPS = ['errors'];

    /** @var list<string> */
    protected array $appPaths = [];

    /**
     * Set the app path(s) searched for the Inertia middleware.
     */
    public function setAppPaths(string ...$paths): void
    {
        $this->appPaths = array_values($paths);
    }

    /**
     * Analyze the discovered HandleInertiaRequests middleware's share() method.
     *
     * @return SharedDataResult|null Null when no Inertia middleware is discovered.
     */
    public function analyze(): ?array
    {
        $middlewareClass = $this->discoverMiddlewareClass();

        return $middlewareClass === null ? null : $this->buildResult($middlewareClass);
    }

    /**
     * Discover the first HandleInertiaRequests middleware class from the app paths.
     *
     * @return class-string|null
     */
    protected function discoverMiddlewareClass(): ?string
    {
        if ($this->appPaths === []) {
            return null;
        }

        foreach ($this->appPaths as $path) {
            $classes = array_keys(ClassMapGenerator::createMap($path));

            foreach ($classes as $class) {
                if (class_exists($class) && is_subclass_of($class, 'Inertia\Middleware')) {
                    return $class;
                }
            }
        }

        return null;
    }

    /**
     * Build the result array from the middleware's share() method.
     *
     * Type resolution priority: #[TsCasts] > @return docblock > AST inference.
     *
     * @param  class-string  $middlewareClass
     * @return SharedDataResult
     */
    protected function buildResult(string $middlewareClass): array
    {
        $analysis = resolve(AstEngine::class)->analyzeMethod($middlewareClass, 'share');

        $tsCasts = $this->parseTsCastsFromMiddleware($middlewareClass);
        $docblockOverrides = $this->parseDocblockFromMiddleware($middlewareClass);

        $resolver = new TsCastsImportResolver;
        $resolvedTsCasts = $resolver->resolve($tsCasts['overrides'], $tsCasts['importPaths']);

        $mergedOverrides = $this->normalizeOverrideKeys(
            array_merge($docblockOverrides, $resolvedTsCasts['overrides'])
        );

        $this->forgetOverriddenChannels($analysis, $mergedOverrides);

        $propsType = $this->buildTypeStringWithOverrides($this->collectProps($analysis), $mergedOverrides);

        return [
            'sharedPageProps' => $propsType,
            'withAllErrors' => $this->resolveWithAllErrors($middlewareClass),
            'typeImports' => $this->mergeTypeImports(
                $this->buildTypeImports($analysis, $propsType),
                $resolvedTsCasts['typeImports'],
            ),
        ];
    }

    /**
     * Read the middleware's `$withAllErrors` default, which decides the errorValueType augmentation.
     *
     * @param  class-string  $middlewareClass
     */
    protected function resolveWithAllErrors(string $middlewareClass): bool
    {
        return (bool) (new ReflectionClass($middlewareClass)->getDefaultProperties()['withAllErrors'] ?? false);
    }

    /**
     * Flatten the analysis into a prop map, later declarations of a key winning.
     *
     * @return SharedPropMap
     */
    protected function collectProps(MethodAnalysis $analysis): array
    {
        $props = [];

        foreach ($analysis->properties as $property) {
            $props[$property['name']] = ['type' => $property['type'], 'optional' => $property['optional']];
        }

        foreach (self::FRAMEWORK_OWNED_PROPS as $name) {
            unset($props[$name]);
        }

        return $props;
    }

    /**
     * Resolve the type imports the inferred props need, keeping only names the rendered type spells.
     *
     * An override replaces a whole prop, so the type it displaced must not keep an import alive.
     *
     * @return TypesImportMap
     */
    protected function buildTypeImports(MethodAnalysis $analysis, string $propsType): array
    {
        $imports = new AnalysisImports()->build($analysis, '')['typeImports'];

        foreach ($imports as $path => $names) {
            $used = array_values(array_filter(
                $names,
                fn (string $name): bool => preg_match(
                    '/(?<![A-Za-z0-9_$.])'.preg_quote($name, '/').'(?![A-Za-z0-9_$])/',
                    $propsType,
                ) === 1,
            ));

            if ($used === []) {
                unset($imports[$path]);

                continue;
            }

            $imports[$path] = $used;
        }

        return $imports;
    }

    /**
     * Merge type import maps and keep their paths and names deterministic.
     *
     * @param  TypesImportMap  ...$maps
     * @return TypesImportMap
     */
    protected function mergeTypeImports(array ...$maps): array
    {
        $imports = [];

        foreach ($maps as $map) {
            foreach ($map as $path => $types) {
                $imports[$path] = array_values(array_unique([
                    ...($imports[$path] ?? []),
                    ...$types,
                ]));
                sort($imports[$path]);
            }
        }

        ksort($imports);

        return $imports;
    }

    /**
     * Drop every FQCN channel belonging to an overridden key, so its import dies with its type.
     *
     * @param  SharedPropMap  $overrides
     */
    protected function forgetOverriddenChannels(MethodAnalysis $analysis, array $overrides): void
    {
        foreach (array_keys($overrides) as $name) {
            unset(
                $analysis->enumResources[$name], $analysis->nestedResources[$name], $analysis->directEnumFqcns[$name],
                $analysis->modelFqcns[$name], $analysis->multiEnumResourceFqcns[$name], $analysis->inlineEnumFqcns[$name],
                $analysis->inlineModelFqcns[$name], $analysis->inlineEnumResourceFqcns[$name],
            );
        }
    }

    /**
     * Parse #[TsCasts] attributes from the middleware class and its share() method.
     *
     * Method-level attributes take priority over class-level, matching Laravel's cast resolution order.
     *
     * @param  class-string  $className
     * @return TsCastsParseResult
     */
    protected function parseTsCastsFromMiddleware(string $className): array
    {
        /** @var ReflectionClass<object> $reflection */
        $reflection = new ReflectionClass($className);

        $attributes = [];

        foreach ($reflection->getAttributes(TsCasts::class) as $attr) {
            $attributes[] = $attr->newInstance();
        }

        if ($reflection->hasMethod('share')) {
            foreach ($reflection->getMethod('share')->getAttributes(TsCasts::class) as $attr) {
                $attributes[] = $attr->newInstance();
            }
        }

        /** @var TsCastsUnpacked $unpacked */
        $unpacked = resolve(TsCastsReader::class)->unpack($attributes);

        return ['overrides' => $unpacked['overrides'], 'importPaths' => $unpacked['importPaths']];
    }

    /**
     * Extract per-key type overrides from the `@return array{...}` docblock on the middleware's share().
     *
     * @param  class-string  $className
     * @return array<string, string>
     */
    protected function parseDocblockFromMiddleware(string $className): array
    {
        /** @var ReflectionClass<object> $reflection */
        $reflection = new ReflectionClass($className);

        if (! $reflection->hasMethod('share')) {
            return [];
        }

        return LaravelTsPublish::parseDocblockReturnArrayShape($reflection->getMethod('share'));
    }

    /**
     * Build a TypeScript object type string, applying overrides where present.
     *
     * @param  SharedPropMap  $props  the AST-inferred props
     * @param  SharedPropMap  $overrides  normalized type overrides keyed by property name
     */
    protected function buildTypeStringWithOverrides(array $props, array $overrides): string
    {
        if ($props === [] && $overrides === []) {
            return 'Record<string, never>';
        }

        $parts = [];

        foreach ($props as $key => $prop) {
            $entry = $overrides[$key] ?? $prop;

            $parts[] = $key.($entry['optional'] ? '?: ' : ': ').$entry['type'];
        }

        foreach ($overrides as $key => $override) {
            if (! array_key_exists($key, $props)) {
                $parts[] = $key.($override['optional'] ? '?: ' : ': ').$override['type'];
            }
        }

        return '{ '.implode(', ', $parts).' }';
    }

    /**
     * Split the docblock parser's key-embedded optional marker back out, so overrides can be matched
     * against the inferred plain property names — otherwise `filters?` misses `filters` and the entry
     * is emitted twice, which TypeScript rejects as a duplicate identifier.
     *
     * @param  array<string, string>  $overrides
     * @return SharedPropMap
     */
    protected function normalizeOverrideKeys(array $overrides): array
    {
        $normalized = [];

        // Insertion order carries priority: #[TsCasts] entries are merged after docblock ones, so an
        // attribute-supplied `filters` still wins over a docblock-supplied `filters?`.
        foreach ($overrides as $key => $type) {
            $optional = str_ends_with($key, '?');
            $name = $optional ? substr($key, 0, -1) : $key;

            $normalized[$name] = ['type' => $type, 'optional' => $optional];
        }

        return $normalized;
    }
}
