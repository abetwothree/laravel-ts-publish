<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Inertia;

use AbeTwoThree\LaravelTsPublish\Analyzers\SurveyorTypeMapper;
use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Support\TsCastsImportResolver;
use Laravel\Ranger\Collectors\InertiaSharedData as InertiaSharedDataCollector;
use Laravel\Ranger\Components\InertiaSharedData as SharedDataComponent;
use Laravel\Surveyor\Types\Contracts\Type;
use ReflectionClass;
use Spatie\StructureDiscoverer\Discover;

/**
 * @phpstan-type TsCastsParseResult = array{
 *     overrides: array<string, string>,
 *     importPaths: array<string, string>,
 * }
 * @phpstan-type SharedDataResult = array{
 *     sharedPageProps: string,
 *     withAllErrors: bool,
 *     importStatements: list<string>,
 * }
 */
class InertiaSharedDataAnalyzer
{
    /** @var list<string> */
    protected array $appPaths = [];

    public function __construct(
        protected InertiaSharedDataCollector $collector,
    ) {}

    /**
     * Set the app path(s) for the Inertia shared data collector.
     */
    public function setAppPaths(string ...$paths): void
    {
        $this->appPaths = array_values($paths);
        $this->collector->setAppPaths(...$paths);
    }

    /**
     * Collect and convert Inertia shared data from HandleInertiaRequests middleware.
     *
     * @return SharedDataResult|null Null when no middleware is discovered.
     */
    public function analyze(): ?array
    {
        $sharedDataComponents = $this->collector->collect();

        if ($sharedDataComponents->isEmpty()) {
            return null;
        }

        /** @var SharedDataComponent $component */
        $component = $sharedDataComponents->first();

        $middlewareClass = $this->discoverMiddlewareClass();

        return $this->buildResult($component, $middlewareClass);
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

        $discovered = Discover::in(...$this->appPaths)
            ->classes()
            ->extending('Inertia\\Middleware')
            ->get();

        /** @var class-string|null */
        return $discovered[0] ?? null;
    }

    /**
     * Build the result array from a SharedDataComponent.
     *
     * Type resolution priority: #[TsCasts] > @return docblock > Surveyor inference.
     *
     * @param  class-string|null  $middlewareClass
     * @return SharedDataResult
     */
    protected function buildResult(SharedDataComponent $component, ?string $middlewareClass): array
    {
        /** @var array<string|int, mixed> $props */
        $props = $component->data->value;

        $tsCasts = $this->parseTsCastsFromMiddleware($middlewareClass);
        $docblockOverrides = $this->parseDocblockFromMiddleware($middlewareClass);

        $resolver = new TsCastsImportResolver;
        $resolvedTsCasts = $resolver->resolve($tsCasts['overrides'], $tsCasts['importPaths']);

        // TsCasts overrides win over docblock; docblock fills gaps Surveyor can't infer
        $mergedOverrides = array_merge($docblockOverrides, $resolvedTsCasts['overrides']);
        $propsType = $this->buildTypeStringWithOverrides($props, $mergedOverrides);

        return [
            'sharedPageProps' => $propsType,
            'withAllErrors' => property_exists($component, 'withAllErrors') ? $component->withAllErrors : false,
            'importStatements' => $resolvedTsCasts['importStatements'],
        ];
    }

    /**
     * Parse #[TsCasts] attributes from the middleware class and its share() method.
     *
     * Method-level attributes take priority over class-level, matching Laravel's cast resolution order.
     *
     * @param  class-string|null  $className
     * @return TsCastsParseResult
     */
    protected function parseTsCastsFromMiddleware(?string $className): array
    {
        if ($className === null || ! class_exists($className)) {
            return ['overrides' => [], 'importPaths' => []];
        }

        /** @var ReflectionClass<object> $reflection */
        $reflection = new ReflectionClass($className);

        $classOverrides = [];
        $methodOverrides = [];

        foreach ($reflection->getAttributes(TsCasts::class) as $attr) {
            $classOverrides = array_merge($classOverrides, $attr->newInstance()->types);
        }

        if ($reflection->hasMethod('share')) {
            foreach ($reflection->getMethod('share')->getAttributes(TsCasts::class) as $attr) {
                $methodOverrides = array_merge($methodOverrides, $attr->newInstance()->types);
            }
        }

        $merged = array_merge($classOverrides, $methodOverrides);

        $overrides = [];
        $importPaths = [];

        foreach ($merged as $key => $value) {
            if (is_array($value)) {
                /** @var array{type: string, import: string} $value */
                $overrides[$key] = $value['type'];
                $importPaths[$key] = $value['import'];
            } else {
                $overrides[$key] = $value;
            }
        }

        return ['overrides' => $overrides, 'importPaths' => $importPaths];
    }

    /**
     * Parse the `@return array{...}` docblock from the middleware's share() method
     * to extract per-key TypeScript type overrides.
     *
     * @param  class-string|null  $className
     * @return array<string, string>
     */
    protected function parseDocblockFromMiddleware(?string $className): array
    {
        if ($className === null || ! class_exists($className)) {
            return [];
        }

        /** @var ReflectionClass<object> $reflection */
        $reflection = new ReflectionClass($className);

        if (! $reflection->hasMethod('share')) {
            return [];
        }

        return LaravelTsPublish::parseDocblockReturnArrayShape($reflection->getMethod('share'));
    }

    /**
     * Build a TypeScript object type string, applying TsCasts overrides where present.
     *
     * @param  array<string|int, mixed>  $props  The Surveyor-analyzed properties.
     * @param  array<string, string>  $overrides  TsCasts type overrides keyed by property name.
     */
    protected function buildTypeStringWithOverrides(array $props, array $overrides): string
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
}
