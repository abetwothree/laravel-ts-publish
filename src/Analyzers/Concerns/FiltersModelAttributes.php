<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Concerns;

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Models\FiltersAttributeKeys;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ReflectedTypeAcceptor;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Support\ImportMaps;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use ReflectionMethod;

/**
 * Handles $this->only([...]), $this->except([...]) and other attribute filter
 * patterns in JsonResource toArray() methods.
 *
 * @phpstan-import-type FqcnQueueMap from MethodAnalysis
 */
trait FiltersModelAttributes
{
    use FiltersAttributeKeys;

    /**
     * Route a $this->only([...]) or $this->except([...]) call, or the same call on $this->resource, to its handler.
     */
    protected function analyzeThisAttributeFilter(MethodCall $call): ?ResourceAnalysis
    {
        if (! $this->filtersOwnModel($call) || ! $call->name instanceof Identifier) {
            return null;
        }

        $methodName = $call->name->toString();
        $keys = $this->extractFilterKeys($call, new ReflectionMethod(Model::class, $methodName));

        if ($keys === null || $keys === []) {
            return null;
        }

        return match ($methodName) {
            'only' => $this->analyzeOnlyFilter($keys),
            'except' => $this->analyzeExceptFilter($keys),
            default => null, // @codeCoverageIgnore
        };
    }

    /**
     * Whether a call is only()/except() on the resource's own model, spelled `$this->…` or `$this->resource->…`.
     *
     * A resource forwards `$this->only()` to `$this->resource`, so both spellings filter the same model.
     */
    protected function filtersOwnModel(MethodCall $call): bool
    {
        return ($this->hasThisReceiver($call) || $this->isProxyFetch($call->var, $this->scope))
            && $call->name instanceof Identifier
            && in_array($call->name->toString(), $this->supportedAttributeFilters(), true);
    }

    /**
     * Analyze $this->only([...]) — include only the listed model attributes. Hidden columns stay
     * in the base analysis: the property set here is the caller's own keys, not derived implicitly.
     *
     * @param  list<string>  $keys
     */
    protected function analyzeOnlyFilter(array $keys): ?ResourceAnalysis
    {
        $fullAnalysis = $this->buildModelDelegatedAnalysis(excludeHidden: false);

        if ($fullAnalysis === null) {
            return null;
        }

        /** @var class-string $modelClass buildModelDelegatedAnalysis() returns null without one */
        $modelClass = $this->scope->modelClass;

        $filtered = $this->filterAnalysisByKeys($fullAnalysis, $keys, include: true);
        $present = array_column($filtered->properties, 'name');
        $acceptor = resolve(ReflectedTypeAcceptor::class);
        $resolver = resolve(ModelAttributeResolver::class);

        // Model::only() returns every requested key, including withCount()/selectRaw() virtuals the schema lacks.
        foreach (array_unique(array_diff($keys, $present)) as $key) {
            $accepted = $acceptor->accept($resolver->resolveAttribute($modelClass, $key, $this->scope->carriesImports));

            if ($accepted !== null) {
                $filtered->addProperty($key, $accepted);
            }
        }

        return $filtered;
    }

    /**
     * Analyze $this->except([...]) — exclude the listed model attributes.
     *
     * @param  list<string>  $keys
     */
    protected function analyzeExceptFilter(array $keys): ?ResourceAnalysis
    {
        $fullAnalysis = $this->buildModelDelegatedAnalysis();

        if ($fullAnalysis === null) {
            return null;
        }

        return $this->filterAnalysisByKeys($fullAnalysis, $keys, include: false);
    }

    /**
     * Filter a ResourceAnalysis to include or exclude properties by key list.
     *
     * A kept key keeps its enum and model queues, each class on them keyed by itself as addProperty() keys it, and the
     * custom imports its type spells, so it imports and aliases every class it names; a left-out key's classes go.
     *
     * @param  list<string>  $keys
     */
    protected function filterAnalysisByKeys(ResourceAnalysis $analysis, array $keys, bool $include): ResourceAnalysis
    {
        $filteredProperties = array_values(array_filter(
            $analysis->properties,
            fn (array $prop): bool => $include
                ? in_array($prop['name'], $keys, true)
                : ! in_array($prop['name'], $keys, true),
        ));

        $kept = array_flip(array_column($filteredProperties, 'name'));
        $inlineEnumFqcns = array_intersect_key($analysis->inlineEnumFqcns, $kept);
        $inlineModelFqcns = array_intersect_key($analysis->inlineModelFqcns, $kept);

        return new ResourceAnalysis(
            properties: $filteredProperties,
            customImports: ImportMaps::spelledIn(
                $analysis->customImports,
                implode(' | ', array_column($filteredProperties, 'type')),
            ),
            directEnumFqcns: [
                ...array_intersect_key($analysis->directEnumFqcns, $kept),
                ...self::selfKeyed($inlineEnumFqcns),
            ],
            modelFqcns: [...array_intersect_key($analysis->modelFqcns, $kept), ...self::selfKeyed($inlineModelFqcns)],
            inlineEnumFqcns: $inlineEnumFqcns,
            inlineModelFqcns: $inlineModelFqcns,
        );
    }

    /**
     * Each class on the queues keyed by itself, as addProperty() self-keys an embedded class for its import.
     *
     * @param  FqcnQueueMap  $queues
     * @return array<class-string, class-string>
     */
    private static function selfKeyed(array $queues): array
    {
        $fqcns = array_values(array_unique(array_merge([], ...array_values($queues))));

        return array_combine($fqcns, $fqcns);
    }
}
