<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Inertia;

use AbeTwoThree\LaravelTsPublish\Analyzers\SurveyorTypeMapper;
use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Http\Resources\Attributes\Collects;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection as LaravelResourceCollection;
use Illuminate\Support\Str;
use Laravel\Ranger\Collectors\InertiaComponents;
use Laravel\Ranger\Collectors\Response as ResponseCollector;
use Laravel\Ranger\Components\InertiaResponse;
use Laravel\Surveyor\Types\Contracts\Type;
use ReflectionClass;
use Throwable;

/**
 * Detects Inertia::render() calls in controller actions via Ranger's static
 * analysis and extracts component names and page-prop types.
 *
 * @phpstan-type PageTypeResult = array{type: string, fqcns: list<class-string>, externalImports: array<string, list<string>>}
 * @phpstan-type InertiaPageData = array{
 *     component: string|list<string>,
 *     pageType: string|list<string>|null,
 *     classFqcns: list<class-string>,
 *     externalImports?: array<string, list<string>>,
 * }
 */
class InertiaPageAnalyzer
{
    public function __construct(
        protected ResponseCollector $responseCollector,
    ) {}

    /**
     * Analyze a controller action and extract Inertia page data.
     *
     * Ranger's parseResponse() returns component name strings for Inertia
     * responses. We collect those names, then look up full InertiaResponse
     * objects via InertiaComponents::getComponent().
     *
     * @param  array{uses: string}  $action  The route action array with 'uses' key (Controller@method format).
     * @return InertiaPageData|null Null when the action does not render an Inertia response.
     */
    public function analyze(array $action): ?array
    {
        // Reset the InertiaComponents static registry so each analyze() call gets
        // only the props declared in *this* controller method, not accumulated state
        // from previous calls that happened to render the same component name.
        $componentsProperty = (new ReflectionClass(InertiaComponents::class))->getProperty('components');
        $componentsProperty->setValue(null, []);

        // Ranger's parseResponse() returns component name strings for Inertia
        // responses despite its docblock claiming InertiaResponse objects.
        /** @var list<string|mixed> $responses */
        $responses = $this->responseCollector->parseResponse($action);

        /** @var list<string> $componentNames */
        $componentNames = array_values(array_filter(
            $responses,
            fn (mixed $response): bool => is_string($response),
        ));

        if ($componentNames === []) {
            return null;
        }

        /** @var list<InertiaResponse> $inertiaResponses */
        $inertiaResponses = array_map(
            fn (string $name): InertiaResponse => InertiaComponents::getComponent($name),
            $componentNames,
        );

        $methodOverrides = [];
        $methodImportMap = [];
        $paginatorModelMap = [];
        $paginatedResourceProps = [];
        $paginatedStaticCollectionProps = [];

        if (str_contains($action['uses'], '@')) {
            [$controllerClass, $methodName] = explode('@', $action['uses'], 2);
            $parsed = $this->parseTsCastsFromMethod($controllerClass, $methodName);
            $methodOverrides = $parsed['overrides'];
            $methodImportMap = $parsed['importMap'];

            try {
                /** @var class-string $typedClass */
                $typedClass = $controllerClass;
                $analyzer = new ControllerPaginatorAnalyzer($typedClass, $methodName);
                $paginatorModelMap = $analyzer->analyze();
                $paginatedResourceProps = $analyzer->analyzePaginatedResourceProps();
                $paginatedStaticCollectionProps = $analyzer->analyzePaginatedStaticCollectionProps();
            } catch (Throwable) {
                // Non-fatal: fall back gracefully to <unknown>
            }
        }

        return $this->buildPageData(
            $inertiaResponses,
            $methodOverrides,
            $methodImportMap,
            $paginatorModelMap,
            $paginatedResourceProps,
            $paginatedStaticCollectionProps
        );
    }

    /**
     * Build the page data from one or more InertiaResponse instances.
     *
     * For a single component, `pageType` is a plain inline type string.
     * For multiple (conditional) components, `pageType` is a list of inline
     * type strings parallel to the `component` list so the transformer can
     * build a keyed map aligned with the component keys.
     *
     * @param  list<InertiaResponse>  $responses
     * @param  array<string, string>  $methodOverrides  TsCasts overrides from the controller method.
     * @param  array<string, list<string>>  $methodImportMap  Import map from TsCasts `import` keys.
     * @param  array<string, class-string>  $paginatorModelMap  Prop key => model FQCN from controller AST analysis.
     * @param  array<string, class-string<object>>  $paginatedResourceProps  Prop key => resource FQCN for paginated resource constructor props.
     * @param  array<string, class-string>  $paginatedStaticCollectionProps  Prop key => resource FQCN for paginated Resource::collection() props.
     * @return InertiaPageData
     */
    protected function buildPageData(
        array $responses,
        array $methodOverrides = [],
        array $methodImportMap = [],
        array $paginatorModelMap = [],
        array $paginatedResourceProps = [],
        array $paginatedStaticCollectionProps = [],
    ): array {
        $components = array_map(
            fn (InertiaResponse $r): string => $r->component,
            $responses,
        );

        $pageTypeResults = array_map(
            fn (InertiaResponse $response): array => $this->buildPageType(
                $response,
                $methodOverrides,
                $paginatorModelMap,
                $paginatedResourceProps,
                $paginatedStaticCollectionProps,
            ),
            $responses,
        );

        $pageTypes = array_map(fn (array $r): string => $r['type'], $pageTypeResults);

        /** @var list<class-string> $allFqcns */
        $allFqcns = array_values(array_unique(array_merge(
            ...array_map(fn (array $r): array => $r['fqcns'], $pageTypeResults),
        )));

        // Aggregate external imports from page type results and method-level TsCasts import map
        /** @var array<string, list<string>> $externalImports */
        $externalImports = $methodImportMap;

        foreach ($pageTypeResults as $result) {
            foreach ($result['externalImports'] as $path => $types) {
                foreach ($types as $type) {
                    if (! in_array($type, $externalImports[$path] ?? [], true)) {
                        $externalImports[$path][] = $type;
                    }
                }
            }
        }

        // Single component → string; multiple (conditional) → list
        $component = count($components) === 1 ? $components[0] : $components;

        // Single type → string; multiple → list (transformer builds keyed map)
        $pageType = count($pageTypes) === 1 ? $pageTypes[0] : $pageTypes;

        return [
            'component' => $component,
            'pageType' => $pageType,
            'classFqcns' => $allFqcns,
            'externalImports' => $externalImports,
        ];
    }

    /**
     * Build the TypeScript type string for a single InertiaResponse.
     *
     * Returns the rewritten type string, the list of PHP FQCNs found within it
     * (for import resolution by the transformer), and any external package imports
     * (e.g. `@tolki/types` entries for pagination/resource types).
     *
     * @param  array<string, string>  $methodOverrides  TsCasts overrides from the controller method.
     * @param  array<string, class-string>  $paginatorModelMap  Prop key => model FQCN from controller AST analysis.
     * @param  array<string, class-string<object>>  $paginatedResourceProps  Prop key => resource FQCN for paginated resource constructor props.
     * @param  array<string, class-string>  $paginatedStaticCollectionProps  Prop key => resource FQCN for paginated Resource::collection() props.
     * @return PageTypeResult
     */
    protected function buildPageType(
        InertiaResponse $response,
        array $methodOverrides = [],
        array $paginatorModelMap = [],
        array $paginatedResourceProps = [],
        array $paginatedStaticCollectionProps = [],
    ): array {
        $sharedData = 'Inertia.SharedData';

        if (count($response->data) === 0 && $methodOverrides === [] && $paginatorModelMap === [] && $paginatedResourceProps === [] && $paginatedStaticCollectionProps === []) {
            return ['type' => $sharedData, 'fqcns' => [], 'externalImports' => []];
        }

        $propsType = $methodOverrides !== []
            ? $this->buildTypeStringWithOverrides($response->data, $methodOverrides)
            : SurveyorTypeMapper::objectToTypeString($response->data);

        $fqcns = SurveyorTypeMapper::extractDotNotationFqcns($propsType);

        [$propsType, $fqcns, $externalImports] = $this->rewriteResourceCollections($propsType, $fqcns);

        [$propsType, $fqcns] = $this->rewritePaginatorGenerics($propsType, $fqcns, $paginatorModelMap);

        $propsType = SurveyorTypeMapper::rewriteDotNotationToBasenames($propsType, $fqcns);

        [$propsType, $fqcns, $resourcePaginationImports] = $this->rewritePaginatedResourceProps(
            $propsType,
            $fqcns,
            $paginatedResourceProps,
        );

        foreach ($resourcePaginationImports as $path => $types) {
            foreach ($types as $type) {
                if (! in_array($type, $externalImports[$path] ?? [], true)) {
                    $externalImports[$path][] = $type;
                }
            }
        }

        [$propsType, $fqcns, $staticCollectionImports] = $this->rewritePaginatedStaticCollectionProps(
            $propsType,
            $fqcns,
            $paginatedStaticCollectionProps,
        );

        foreach ($staticCollectionImports as $path => $types) {
            foreach ($types as $type) {
                if (! in_array($type, $externalImports[$path] ?? [], true)) {
                    $externalImports[$path][] = $type;
                }
            }
        }

        return [
            'type' => $sharedData.' & '.$propsType,
            'fqcns' => $fqcns,
            'externalImports' => $externalImports,
        ];
    }

    /**
     * Build a TypeScript object type string, applying TsCasts overrides for specific props.
     *
     * @param  array<string|int, mixed>  $props  Surveyor-analyzed properties.
     * @param  array<string, string>  $overrides  TsCasts type overrides keyed by property name.
     */
    private function buildTypeStringWithOverrides(array $props, array $overrides): string
    {
        if ($props === [] && $overrides === []) {
            return 'Record<string, never>';
        }

        $parts = [];

        foreach ($props as $key => $value) {
            if (isset($overrides[$key])) {
                $parts[] = $key.': '.$overrides[$key];

                continue;
            }

            $optional = $value instanceof Type && $value->isOptional();
            $separator = $optional ? '?: ' : ': ';

            if (is_array($value)) {
                $tsType = SurveyorTypeMapper::objectToTypeString($value);
            } elseif ($value instanceof Type) {
                $tsType = SurveyorTypeMapper::convert($value);
            } else {
                $tsType = 'unknown';
            }

            $parts[] = $key.$separator.$tsType;
        }

        // Add any override keys not already in the Surveyor-analyzed props
        foreach ($overrides as $key => $type) {
            if (! array_key_exists($key, $props)) {
                $parts[] = $key.': '.$type;
            }
        }

        return '{ '.implode(', ', $parts).' }';
    }

    /**
     * Rewrite paginator generic types in the type string based on the paginator-model map.
     *
     * For each entry in `$paginatorModelMap`, searches the type string for
     * `propKey: PaginatorFqcn<unknown>` (using dot-notation) and replaces `<unknown>`
     * with the model's dot-notation class name. The model FQCN is appended to `$fqcns`
     * so the existing import pipeline resolves it to a relative import path.
     *
     * @param  list<class-string>  $fqcns
     * @param  array<string, class-string>  $paginatorModelMap  prop key => model FQCN
     * @return array{string, list<class-string>}
     */
    protected function rewritePaginatorGenerics(
        string $typeString,
        array $fqcns,
        array $paginatorModelMap,
    ): array {
        if ($paginatorModelMap === []) {
            return [$typeString, $fqcns];
        }

        foreach ($paginatorModelMap as $propKey => $modelFqcn) {
            $modelDotNotation = str_replace('\\', '.', $modelFqcn);

            foreach (array_keys(SurveyorTypeMapper::TOLKI_TYPES_MAP) as $paginatorFqcn) {
                $paginatorDot = str_replace('\\', '.', $paginatorFqcn);
                $search = $propKey.': '.$paginatorDot.'<unknown>';
                $replace = $propKey.': '.$paginatorDot.'<'.$modelDotNotation.'>';

                if (str_contains($typeString, $search)) {
                    $typeString = str_replace($search, $replace, $typeString);

                    if (! in_array($modelFqcn, $fqcns, true)) {
                        $fqcns[] = $modelFqcn;
                    }

                    break;
                }
            }
        }

        return [$typeString, $fqcns];
    }

    /**
     * Rewrite prop types for resource objects constructed with a paginated variable.
     *
     * For non-flat resources/collections (those with `$wrap !== null`), appends `& ResourcePagination`
     * and adds `ResourcePagination` to the `@tolki/types` external imports.
     *
     * For flat collections (`$wrap === null`), replaces the prop type entirely with
     * `JsonResourcePaginator<SingularType>` and adds `JsonResourcePaginator` to external imports.
     * The flat collection FQCN is removed from `$fqcns` and the singular resource FQCN is added.
     *
     * @param  list<class-string>  $fqcns
     * @param  array<string, class-string<object>>  $paginatedResourceProps  Prop key => resource FQCN.
     * @return array{string, list<class-string>, array<string, list<string>>}
     */
    protected function rewritePaginatedResourceProps(string $typeString, array $fqcns, array $paginatedResourceProps): array
    {
        /** @var array<string, list<string>> $externalImports */
        $externalImports = [];

        if ($paginatedResourceProps === []) {
            return [$typeString, $fqcns, $externalImports];
        }

        foreach ($paginatedResourceProps as $propKey => $resourceFqcn) {
            if (! class_exists($resourceFqcn)) {
                continue;
            }

            $reflection = new ReflectionClass($resourceFqcn);
            $baseName = $reflection->getShortName();
            $defaults = $reflection->getDefaultProperties();

            $isFlat = is_a($resourceFqcn, LaravelResourceCollection::class, true)
                && array_key_exists('wrap', $defaults)
                && $defaults['wrap'] === null;

            $pattern = '/\b'.preg_quote($propKey, '/').':\s+'.preg_quote($baseName, '/').'(?![A-Za-z0-9_])/';

            if ($isFlat) {
                $singularFqcn = $this->resolveSingularResourceFqcn($resourceFqcn);
                $singularBase = $singularFqcn !== null ? (new ReflectionClass($singularFqcn))->getShortName() : 'unknown';

                $typeString = (string) preg_replace($pattern, $propKey.': JsonResourcePaginator<'.$singularBase.'>', $typeString);

                $externalImports['@tolki/types'][] = 'JsonResourcePaginator';

                /** @var list<class-string> $fqcns */
                $fqcns = array_values(array_filter($fqcns, fn (string $f) => $f !== $resourceFqcn));

                if ($singularFqcn !== null && ! in_array($singularFqcn, $fqcns, true)) {
                    $fqcns[] = $singularFqcn;
                }
            } else {
                $typeString = (string) preg_replace($pattern, $propKey.': '.$baseName.' & ResourcePagination', $typeString);

                $externalImports['@tolki/types'][] = 'ResourcePagination';
            }
        }

        /** @var list<class-string> $fqcns */
        return [$typeString, $fqcns, $externalImports];
    }

    /**
     * Rewrite `Resource::collection($paginatedVar)` props from `AnonymousResourceCollection<ResourceName>`
     * to `JsonResourcePaginator<ResourceName>` in the type string.
     *
     * After `rewritePaginatorGenerics()` has replaced `<unknown>` with the resource FQCN and
     * `rewriteDotNotationToBasenames()` has shortened the dot-notation, the type string contains
     * entries like `propKey: AnonymousResourceCollection<WarehouseResource>`. This method replaces
     * those entries with `propKey: JsonResourcePaginator<WarehouseResource>` for props whose
     * collection argument was a paginated variable.
     *
     * @param  list<class-string>  $fqcns
     * @param  array<string, class-string>  $paginatedStaticCollectionProps  Prop key => resource FQCN.
     * @return array{string, list<class-string>, array<string, list<string>>}
     */
    protected function rewritePaginatedStaticCollectionProps(string $typeString, array $fqcns, array $paginatedStaticCollectionProps): array
    {
        /** @var array<string, list<string>> $externalImports */
        $externalImports = [];

        if ($paginatedStaticCollectionProps === []) {
            return [$typeString, $fqcns, $externalImports];
        }

        foreach ($paginatedStaticCollectionProps as $propKey => $resourceFqcn) {
            if (! class_exists($resourceFqcn)) {
                continue;
            }

            $baseName = (new ReflectionClass($resourceFqcn))->getShortName();

            // At this point the type string has: `propKey: AnonymousResourceCollection<unknown>`
            // (the non-paginated path fills in the generic via rewritePaginatorGenerics, but
            // paginated props bypass that — they're not in paginatorModelMap — so <unknown> remains)
            $pattern = '/\b'.preg_quote($propKey, '/').': AnonymousResourceCollection<unknown>/';
            $typeString = (string) preg_replace($pattern, $propKey.': JsonResourcePaginator<'.$baseName.'>', $typeString);

            $externalImports['@tolki/types'][] = 'JsonResourcePaginator';

            if (! in_array($resourceFqcn, $fqcns, true)) {
                $fqcns[] = $resourceFqcn;
            }
        }

        /** @var list<class-string> $fqcns */
        return [$typeString, $fqcns, $externalImports];
    }

    /**
     * Detect ResourceCollection subclasses in the FQCNs list and rewrite them in the type string.
     *
     * @param  list<class-string>  $fqcns
     * @return array{string, list<class-string>, array<string, list<string>>}
     */
    protected function rewriteResourceCollections(string $typeString, array $fqcns): array
    {
        /** @var array<string, list<string>> $externalImports */
        $externalImports = [];

        /** @var list<class-string> $rewrittenFqcns */
        $rewrittenFqcns = [];

        foreach ($fqcns as $fqcn) {
            // FQCNs in TOLKI_TYPES_MAP are handled upstream by resolvePageTypeImports()
            if (isset(SurveyorTypeMapper::TOLKI_TYPES_MAP[$fqcn])) {
                $rewrittenFqcns[] = $fqcn;

                continue;
            }

            // Only rewrite concrete ResourceCollection subclasses
            if (! class_exists($fqcn) || ! is_a($fqcn, LaravelResourceCollection::class, true)) {
                $rewrittenFqcns[] = $fqcn;

                continue;
            }

            $dotNotation = str_replace('\\', '.', $fqcn);
            $collectionName = class_basename($fqcn);
            $typeString = str_replace($dotNotation, $collectionName, $typeString);

            $rewrittenFqcns[] = $fqcn;
        }

        return [$typeString, $rewrittenFqcns, $externalImports];
    }

    /**
     * Resolve the singular resource FQCN for a ResourceCollection subclass.
     *
     * Checks the `$collects` property first, then falls back to the naming convention
     * (`XCollection` → `XResource` in the same namespace).
     *
     * @param  class-string  $collectionFqcn
     * @return class-string|null
     */
    protected function resolveSingularResourceFqcn(string $collectionFqcn): ?string
    {
        $reflection = new ReflectionClass($collectionFqcn);

        // Priority 0: #[Collects] attribute
        $collectsAttrs = $reflection->getAttributes(Collects::class);

        if ($collectsAttrs !== []) {
            $collectsClass = $collectsAttrs[0]->newInstance()->class;

            if (class_exists($collectsClass) && is_a($collectsClass, JsonResource::class, true)) {
                return $collectsClass;
            }
        }

        /** @var array<string, mixed> $defaults */
        $defaults = $reflection->getDefaultProperties();
        $collects = $defaults['collects'] ?? null;

        if (is_string($collects) && class_exists($collects) && is_a($collects, JsonResource::class, true)) {
            return $collects;
        }

        $className = $reflection->getShortName();
        $namespace = $reflection->getNamespaceName();

        if (str_ends_with($className, 'Collection')) {
            $base = substr($className, 0, -10);

            $candidate = $namespace.'\\'.$base.'Resource';

            if (class_exists($candidate) && is_a($candidate, JsonResource::class, true)) {
                return $candidate;
            }

            $candidate = $namespace.'\\'.$base; // @codeCoverageIgnoreStart

            if (class_exists($candidate) && is_a($candidate, JsonResource::class, true)) {
                return $candidate;
            } // @codeCoverageIgnoreEnd
        }

        return null;
    }

    /**
     * Parse TsCasts attribute from a controller method.
     *
     * Reads `#[TsCasts([...])]` from the given controller method and returns
     * the type overrides map and any import map for external type packages.
     *
     * @return array{overrides: array<string, string>, importMap: array<string, list<string>>}
     */
    protected function parseTsCastsFromMethod(string $controllerClass, string $methodName): array
    {
        if (! class_exists($controllerClass)) {
            return ['overrides' => [], 'importMap' => []];
        }

        $reflection = new ReflectionClass($controllerClass);

        if (! $reflection->hasMethod($methodName)) {
            return ['overrides' => [], 'importMap' => []];
        }

        $attrs = $reflection->getMethod($methodName)->getAttributes(TsCasts::class);

        if ($attrs === []) {
            return ['overrides' => [], 'importMap' => []];
        }

        /** @var TsCasts $tsCasts */
        $tsCasts = $attrs[0]->newInstance();

        $overrides = [];
        $importMap = [];

        foreach ($tsCasts->types as $prop => $value) {
            if (is_array($value)) {
                $overrides[$prop] = $value['type'];
                /** @var string $importPath */
                $importPath = $value['import'];

                foreach (LaravelTsPublish::extractImportableTypes($value['type']) as $typeName) {
                    $importMap[$importPath][] = $typeName;
                }
            } else {
                $overrides[$prop] = $value;
            }
        }

        return ['overrides' => $overrides, 'importMap' => $importMap];
    }

    /**
     * Convert a component name to a fully-qualified Inertia namespace path.
     *
     * @example "Dashboard" → "Inertia.Pages.Dashboard"
     * @example "Settings/General" → "Inertia.Pages.Settings.General"
     */
    public function componentToFqn(string $component): string
    {
        $normalized = str_replace('::', '/', $component);

        return collect(explode('/', $normalized))
            ->map(fn (string $part): string => Str::studly($part))
            ->prepend('Inertia.Pages')
            ->implode('.');
    }
}
