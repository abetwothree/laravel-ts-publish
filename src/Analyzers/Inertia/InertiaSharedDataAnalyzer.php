<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Inertia;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisImports;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\IndexSignatureReconciler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\TsCastsReader;
use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TsCastsImportResolver;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeString as TsTypeStringService;
use Composer\ClassMapGenerator\ClassMapGenerator;
use Illuminate\Support\Facades\Config;
use ReflectionClass;

/**
 * @phpstan-import-type TsCastsUnpacked from TsCastsReader
 * @phpstan-import-type TypesImportMap from Datable
 *
 * @phpstan-type TsCastsParseResult = array{
 *     overrides: array<string, string>,
 *     importPaths: array<string, string>,
 *     optionalOverrides?: array<string, bool>,
 * }
 * @phpstan-type SharedDataResult = array{
 *     sharedPageProps: string,
 *     withAllErrors: bool,
 *     typeImports: TypesImportMap,
 *     valueImports: TypesImportMap,
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
        $keys = array_column($analysis->properties, 'name');

        // Each location decides alone, so share() outranks the class whatever the spelling; a loser imports nothing.
        $targets = resolve(TsCastsReader::class)
            ->castTargets($this->tsCastsAttributesFromMiddleware($middlewareClass), $keys);
        $tsCasts = $this->parseTsCastsFromMiddleware($middlewareClass);
        JsEmitter::warnAmbiguousCasts($middlewareClass, array_keys($tsCasts['overrides']), $keys);
        $docblockOverrides = $this->parseDocblockFromMiddleware($middlewareClass);

        $resolver = new TsCastsImportResolver;
        $resolvedTsCasts = $resolver->resolve(
            JsEmitter::retargetCasts($tsCasts['overrides'], $targets),
            JsEmitter::retargetCasts($tsCasts['importPaths'], $targets),
        );

        $mergedOverrides = JsEmitter::castsByKey(
            $this->normalizeOverrideKeys(
                array_replace($docblockOverrides, $resolvedTsCasts['overrides']),
                JsEmitter::retargetCasts($tsCasts['optionalOverrides'] ?? [], $targets),
                $this->propFlagsOutsideDocblock($analysis, $docblockOverrides),
            ),
            $keys,
        );

        // The overrides are laid over the props below, so they can add or retype a key a signature covers.
        resolve(IndexSignatureReconciler::class)
            ->reconcile($analysis, array_map(fn (array $override): string => $override['type'], $mergedOverrides));

        $this->forgetOverriddenChannels($analysis, $mergedOverrides);

        $propsType = $this->buildTypeStringWithOverrides(
            $this->rewriteEnumResourceTypes($this->collectProps($analysis), $analysis),
            $mergedOverrides,
        );

        $inferredImports = $this->buildInferredImports($analysis, $propsType);

        return [
            'sharedPageProps' => $propsType,
            'withAllErrors' => $this->resolveWithAllErrors($middlewareClass),
            'typeImports' => $this->mergeTypeImports(
                $inferredImports['typeImports'],
                $resolvedTsCasts['typeImports'],
            ),
            'valueImports' => $inferredImports['valueImports'],
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
     * Rewrite each EnumResource-wrapped prop to `AsEnum<typeof Const>`, as resource generation does.
     *
     * Applied per property rather than to the whole rendered type, so an enum some other prop still
     * reads bare keeps that prop's plain type name — and with it its type import.
     *
     * @param  SharedPropMap  $props
     * @return SharedPropMap
     */
    protected function rewriteEnumResourceTypes(array $props, MethodAnalysis $analysis): array
    {
        if (! Config::boolean('ts-publish.enums.use_tolki_package')) {
            return $props;
        }

        foreach ($analysis->enumResources as $name => $fqcn) {
            if (! isset($props[$name])) {
                continue;
            }

            $tsInfo = LaravelTsPublish::toTsType($fqcn);

            $props[$name]['type'] = TsTypeString::substituteEnumType(
                $props[$name]['type'],
                $tsInfo['enumTypes'][0] ?? class_basename($fqcn).'Type',
                'AsEnum<typeof '.($tsInfo['enums'][0] ?? class_basename($fqcn)).'>',
            );
        }

        return $props;
    }

    /**
     * Resolve the imports the inferred props need, keeping only names the rendered type spells.
     *
     * An override replaces a whole prop, so the type it displaced must not keep an import alive.
     *
     * @return array{typeImports: TypesImportMap, valueImports: TypesImportMap}
     */
    protected function buildInferredImports(MethodAnalysis $analysis, string $propsType): array
    {
        $imports = new AnalysisImports()->build($analysis, '');

        return [
            'typeImports' => $this->keepSpelledNames($imports['typeImports'], $propsType),
            'valueImports' => $this->keepSpelledNames($imports['valueImports'], $propsType),
        ];
    }

    /**
     * Drop every import name the rendered props type never spells, and any path left empty.
     *
     * @param  TypesImportMap  $imports
     * @return TypesImportMap
     */
    protected function keepSpelledNames(array $imports, string $propsType): array
    {
        foreach ($imports as $path => $names) {
            $used = array_values(array_filter(
                $names,
                fn (string $name): bool => TsTypeString::typeNameOccursIn($name, $propsType),
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
            $analysis->forgetChannels((string) $name);
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
        /** @var TsCastsUnpacked $unpacked */
        $unpacked = resolve(TsCastsReader::class)->unpack($this->tsCastsAttributesFromMiddleware($className));

        return [
            'overrides' => $unpacked['overrides'],
            'importPaths' => $unpacked['importPaths'],
            'optionalOverrides' => $unpacked['optionalOverrides'],
        ];
    }

    /**
     * The middleware's #[TsCasts] instances in precedence order: the class, then its share() method.
     *
     * @param  class-string  $className
     * @return list<TsCasts>
     */
    protected function tsCastsAttributesFromMiddleware(string $className): array
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

        return $attributes;
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
            return TsTypeStringService::EMPTY_OBJECT;
        }

        $parts = [];

        // An override keeps its prop's place, and one no prop has goes after them.
        foreach (array_replace($props, $overrides) as $key => $entry) {
            $key = (string) $key;
            $parts[] = JsEmitter::validJsObjectKey($key, allowIndexSignature: true)
                .($entry['optional'] && ! JsEmitter::isIndexSignatureKey($key) ? '?: ' : ': ').$entry['type'];
        }

        return '{ '.implode(', ', $parts).' }';
    }

    /**
     * Split the docblock parser's key-embedded optional marker back out, so overrides can be matched
     * against the inferred plain property names — otherwise `filters?` misses `filters` and the entry
     * is emitted twice, which TypeScript rejects as a duplicate identifier. Also settles an optional signature.
     *
     * @param  array<string, string>  $overrides
     * @param  array<string, bool>  $optionalOverrides  a #[TsCasts] entry's own `optional` flag, which outranks a `?`
     * @param  array<string, bool>  $propFlags  each prop's own flag, which a cast that says nothing keeps unless an
     *                                          earlier entry decides
     * @return SharedPropMap
     */
    protected function normalizeOverrideKeys(array $overrides, array $optionalOverrides = [], array $propFlags = []): array
    {
        $normalized = [];

        // Insertion order carries priority: #[TsCasts] entries are merged after docblock ones, so an
        // attribute-supplied `filters` still wins over a docblock-supplied `filters?`, and keeps its `?`.
        foreach ($overrides as $key => $type) {
            $key = (string) $key; // PHP stores a numeric key such as '42' as an int.
            $suffixed = str_ends_with($key, '?');
            $name = $suffixed ? substr($key, 0, -1) : $key;
            $optional = $optionalOverrides[$key] ?? ($suffixed || ($normalized[$name]['optional'] ?? $propFlags[$name] ?? false));

            $normalized[$name] = JsEmitter::signatureSafeMember($name, $type, $optional);
        }

        return $normalized;
    }

    /**
     * Each prop's own optional flag on a key the docblock leaves alone, which a cast that says nothing about it keeps.
     *
     * @param  array<string, string>  $docblockOverrides
     * @return array<string, bool>
     */
    protected function propFlagsOutsideDocblock(MethodAnalysis $analysis, array $docblockOverrides): array
    {
        $docblockNames = array_map(fn (int|string $key): string => rtrim((string) $key, '?'), array_keys($docblockOverrides));

        return array_diff_key(array_column($analysis->properties, 'optional', 'name'), array_flip($docblockNames));
    }
}
