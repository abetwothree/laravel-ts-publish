<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Concerns;

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\FiltersAttributeKeys;
use AbeTwoThree\LaravelTsPublish\Ast\ReflectedTypeAcceptor;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use ReflectionMethod;

/**
 * Handles $this->only([...]), $this->except([...]) and other attribute filter
 * patterns in JsonResource toArray() methods.
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
        return ($this->hasThisReceiver($call) || $this->isResourceFetch($call->var))
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
            $accepted = $acceptor->accept($resolver->resolveAttribute($modelClass, $key));

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

        $filteredEnumFqcns = array_filter(
            $analysis->directEnumFqcns,
            fn (string $key): bool => $include
                ? in_array($key, $keys, true)
                : ! in_array($key, $keys, true),
            ARRAY_FILTER_USE_KEY,
        );

        $filteredModelFqcns = array_filter(
            $analysis->modelFqcns,
            fn (string $key): bool => $include
                ? in_array($key, $keys, true)
                : ! in_array($key, $keys, true),
            ARRAY_FILTER_USE_KEY,
        );

        return new ResourceAnalysis(
            properties: $filteredProperties,
            directEnumFqcns: $filteredEnumFqcns,
            modelFqcns: $filteredModelFqcns,
        );
    }
}
