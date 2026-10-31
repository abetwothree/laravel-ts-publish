<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Inertia;

use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\RewritesEnumResourceProps;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\CastChannels;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\FileImportNames;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\IndexSignatureReconciler;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\TsCastsReader;
use AbeTwoThree\LaravelTsPublish\Ast\Parsing\ParentCallChain;
use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Support\ComposedClassTypes;
use AbeTwoThree\LaravelTsPublish\Support\ImportMaps;
use AbeTwoThree\LaravelTsPublish\Support\TopLevelRenumbering;
use AbeTwoThree\LaravelTsPublish\Support\TsCastsImportResolver;
use Composer\ClassMapGenerator\ClassMapGenerator;
use ReflectionClass;

/**
 * @phpstan-import-type TsCastsUnpacked from TsCastsReader
 * @phpstan-import-type TypesImportMap from Datable
 * @phpstan-import-type ComposedFile from FileImportNames
 * @phpstan-import-type PrintedMember from FileImportNames
 * @phpstan-import-type ShapeTypeInfo from \AbeTwoThree\LaravelTsPublish\LaravelTsPublish
 * @phpstan-import-type CastMap from MethodAnalysis
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
 * @phpstan-type SharedPropMap = array<array-key, OverrideEntry>
 */
class InertiaSharedDataAnalyzer
{
    use RewritesEnumResourceProps;

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
        $analysis = resolve(AstEngine::class)->analyzeMethod($middlewareClass, 'share', inertiaProps: true);
        $keys = array_column($analysis->properties, 'name');

        // Each location decides alone, so share() outranks the class whatever the spelling; a loser imports nothing.
        $targets = resolve(TsCastsReader::class)
            ->castTargets($this->tsCastsAttributesFromMiddleware($middlewareClass), $keys);
        $tsCasts = $this->parseTsCastsFromMiddleware($middlewareClass);
        JsEmitter::warnAmbiguousCasts($middlewareClass, array_keys($tsCasts['overrides']), $keys);
        $docblock = $this->parseDocblockFromMiddleware($middlewareClass);
        $docblockOverrides = array_map(fn (array $info): string => $info['type'], $docblock);

        $importPaths = JsEmitter::retargetCasts($tsCasts['importPaths'], $targets);
        $resolver = new TsCastsImportResolver;
        $resolvedTsCasts = $resolver->resolve(JsEmitter::retargetCasts($tsCasts['overrides'], $targets), $importPaths);
        $this->carryDocblockImports($analysis, $docblock, $resolvedTsCasts['overrides']);

        $overrides = $this->normalizeOverrideKeys(
            array_replace($docblockOverrides, $resolvedTsCasts['overrides']),
            JsEmitter::retargetCasts($tsCasts['optionalOverrides'] ?? [], $targets),
            $this->propFlagsOutsideDocblock($analysis, $docblockOverrides),
        );
        $overrideTargets = JsEmitter::castTargets(array_map(strval(...), array_keys($overrides)), $keys);
        $mergedOverrides = JsEmitter::retargetCasts($overrides, $overrideTargets);
        $castPaths = JsEmitter::retargetCasts(
            $this->castPathsByName($resolvedTsCasts['overrides'], $importPaths),
            $overrideTargets,
        );
        $docblockByKey = JsEmitter::retargetCasts($this->docblockByName($docblock), $overrideTargets);

        // The overrides are laid over the props below, so they can add or retype a key a signature covers.
        resolve(IndexSignatureReconciler::class)->reconcile(
            $analysis,
            array_map(fn (array $override): string => $override['type'], $mergedOverrides),
            subject: $middlewareClass,
        );

        $castsInForce = $this->castsInForce($analysis, $mergedOverrides, $castPaths, $docblockByKey);
        resolve(CastChannels::class)->fit($analysis, $castsInForce);

        $props = $this->rewriteEnumResourceTypes($this->collectProps($analysis), $analysis);
        $members = $this->buildMembersWithOverrides(
            $props,
            $mergedOverrides,
            $this->renumberTopLevel($props, $mergedOverrides, $middlewareClass),
        );
        $file = $this->composeSharedData($analysis, $members, $resolvedTsCasts['typeImports']);
        ComposedClassTypes::warnOfSharedNames($middlewareClass, ComposedClassTypes::classesIn($analysis->customImports));

        return [
            'sharedPageProps' => $file['types'][0],
            'withAllErrors' => $this->resolveWithAllErrors($middlewareClass),
            'typeImports' => ImportMaps::merge($file['typeImports'], $resolvedTsCasts['typeImports']),
            'valueImports' => $file['valueImports'],
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
     * Join the printed members into the props type, same-named classes aliased apart, beside the imports it spells.
     *
     * @param  list<PrintedMember>  $members
     * @param  TypesImportMap  $castImports  the imports of the casts in force
     * @return ComposedFile
     */
    protected function composeSharedData(MethodAnalysis $analysis, array $members, array $castImports): array
    {
        $customImports = ImportMaps::merge($analysis->customImports, $castImports);
        $member = FileImportNames::objectMember($analysis, $members, brought: ImportMaps::localNames($customImports));

        return FileImportNames::compose([$member], $customImports, '');
    }

    /**
     * Parse the #[TsCasts] attributes of the middleware's share() chain, a later location winning a key.
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
     * The #[TsCasts] instances of the middleware's share() chain, root first, each class's before its own share()'s.
     *
     * @param  class-string  $className
     * @return list<TsCasts>
     */
    protected function tsCastsAttributesFromMiddleware(string $className): array
    {
        $declaring = resolve(ParentCallChain::class)->declaringClasses($className, 'share');
        $attributes = [];

        foreach (array_reverse($this->castClasses($className, $declaring)) as $class) {
            $located = $class->getAttributes(TsCasts::class);

            if (in_array($class->getName(), $declaring, true)) {
                $located = [...$located, ...$class->getMethod('share')->getAttributes(TsCasts::class)];
            }

            foreach ($located as $attr) {
                $attributes[] = $attr->newInstance();
            }
        }

        return $attributes;
    }

    /**
     * Add the custom imports of each docblock entry no #[TsCasts] entry replaces, as a key a cast replaces imports the
     * cast's alone.
     *
     * @param  array<string, ShapeTypeInfo>  $docblock
     * @param  array<array-key, string>  $castOverrides
     */
    protected function carryDocblockImports(MethodAnalysis $analysis, array $docblock, array $castOverrides): void
    {
        $imports = [];

        foreach ($docblock as $key => $info) {
            if (! array_key_exists(rtrim((string) $key, '?'), $castOverrides) && $info['customImports'] !== []) {
                $imports[] = $info['customImports'];
            }
        }

        if ($imports !== []) {
            $analysis->customImports = ImportMaps::merge($analysis->customImports, ...$imports);
        }
    }

    /**
     * Extract per-key type overrides from the `@return array{...}` docblock on the middleware's share(), each with the
     * custom imports of the overrides it spells.
     *
     * @param  class-string  $className
     * @return array<string, ShapeTypeInfo>
     */
    protected function parseDocblockFromMiddleware(string $className): array
    {
        /** @var ReflectionClass<object> $reflection */
        $reflection = new ReflectionClass($className);

        if (! $reflection->hasMethod('share')) {
            return [];
        }

        return LaravelTsPublish::docblockReturnShapeInfos($reflection->getMethod('share'));
    }

    /**
     * The key Laravel sends each top-level prop on, as the page merge renumbers shared data's int keys; warns for each.
     *
     * @param  SharedPropMap  $props
     * @param  SharedPropMap  $overrides
     * @return array<array-key, string>
     */
    protected function renumberTopLevel(array $props, array $overrides, string $middlewareClass): array
    {
        $renumbered = TopLevelRenumbering::renumber(array_map(strval(...), array_keys(array_replace($props, $overrides))));
        TopLevelRenumbering::warnRenumbered($middlewareClass, 'shared data', $renumbered);

        return $renumbered;
    }

    /**
     * Print each prop as an object type's member, an override in its place, on the key Laravel sends it on.
     *
     * @param  SharedPropMap  $props  the AST-inferred props
     * @param  SharedPropMap  $overrides  normalized type overrides keyed by property name
     * @param  array<array-key, string>  $renumbered  the key Laravel sends each prop on, by its name
     * @return list<PrintedMember>
     */
    protected function buildMembersWithOverrides(array $props, array $overrides, array $renumbered = []): array
    {
        $members = [];

        // An override keeps its prop's place, and one no prop has goes after them.
        foreach (array_replace($props, $overrides) as $name => $entry) {
            $key = $renumbered[$name] ?? (string) $name;
            $members[] = [
                'key' => JsEmitter::validJsObjectKey($key, allowIndexSignature: true)
                    .($entry['optional'] && ! JsEmitter::isIndexSignatureKey($key) ? '?: ' : ': '),
                'type' => $entry['type'],
                'source' => $name,
            ];
        }

        return $members;
    }

    /**
     * Split the docblock parser's key-embedded optional marker back out, so overrides can be matched
     * against the inferred plain property names — otherwise `filters?` misses `filters` and the entry
     * is emitted twice, which TypeScript rejects as a duplicate identifier. Also settles an optional signature.
     *
     * @param  array<array-key, string>  $overrides
     * @param  array<array-key, bool>  $optionalOverrides  a #[TsCasts] entry's own `optional` flag, over a `?`
     * @param  array<array-key, bool>  $propFlags  each prop's own flag, which a cast that says nothing keeps unless an
     *                                             earlier entry decides
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

    /**
     * Each class from the middleware up to the deepest declaring a share() the analysis reads.
     *
     * PHP gives no class its parent's attributes, so each is a cast location of its own.
     *
     * @param  class-string  $className
     * @param  list<class-string>  $declaring  the classes declaring each share() the analysis reads
     * @return non-empty-list<ReflectionClass<object>>
     */
    private function castClasses(string $className, array $declaring): array
    {
        $class = new ReflectionClass($className);
        $deepest = $declaring === [] ? $className : $declaring[array_key_last($declaring)];
        $chain = [$class];

        while ($class->getName() !== $deepest && ($class = $class->getParentClass()) !== false) {
            $chain[] = $class;
        }

        return $chain;
    }

    /**
     * Each key a #[TsCasts] entry names, without the `?` normalizeOverrideKeys() drops, with the entry's import path.
     *
     * @param  array<array-key, string>  $casts
     * @param  array<array-key, string>  $paths
     * @return array<array-key, string|null>
     */
    private function castPathsByName(array $casts, array $paths): array
    {
        $byName = [];

        foreach (array_keys($casts) as $key) {
            $name = (string) $key;
            $byName[str_ends_with($name, '?') ? substr($name, 0, -1) : $name] = $paths[$key] ?? null;
        }

        return $byName;
    }

    /**
     * Each docblock entry by the key it retypes, without the `?` normalizeOverrideKeys() drops.
     *
     * @param  array<string, ShapeTypeInfo>  $docblock
     * @return array<array-key, ShapeTypeInfo>
     */
    private function docblockByName(array $docblock): array
    {
        $byName = [];

        foreach ($docblock as $key => $info) {
            $byName[rtrim((string) $key, '?')] = $info;
        }

        return $byName;
    }

    /**
     * The cast in force for each key a #[TsCasts] entry, the share() docblock or a share() helper's own method cast
     * retypes, with its imports, and for a docblock entry the class behind each token it names.
     *
     * @param  SharedPropMap  $overrides
     * @param  array<array-key, string|null>  $paths  each key a #[TsCasts] entry wins, with that entry's import path
     * @param  array<array-key, ShapeTypeInfo>  $docblock  each docblock entry, by the key it retypes
     * @return CastMap
     */
    private function castsInForce(MethodAnalysis $analysis, array $overrides, array $paths, array $docblock): array
    {
        $inForce = $analysis->casts;

        foreach ($overrides as $key => $override) {
            if (array_key_exists($key, $paths)) {
                $inForce[$key] = ['type' => $override['type'], 'import' => $paths[$key]];
            } elseif (isset($docblock[$key])) {
                $inForce[$key] = [
                    'type' => $override['type'],
                    'import' => null,
                    'customImports' => $docblock[$key]['customImports'],
                    'tokenFqcns' => $docblock[$key]['tokenFqcns'] ?? [],
                ];
            }
        }

        return $inForce;
    }
}
