<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Concerns;

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\FiltersAttributeKeys;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
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
     * Route a $this->only([...]) or $this->except([...]) call to the appropriate handler.
     */
    protected function analyzeThisAttributeFilter(MethodCall $call): ?ResourceAnalysis
    {
        if (! ($call->var instanceof Variable && $call->var->name === 'this' && $call->name instanceof Identifier)) {
            return null;
        }

        $methodName = $call->name->toString();

        if (! in_array($methodName, $this->supportedAttributeFilters(), true)) {
            return null; // @codeCoverageIgnore
        }

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

        return $this->filterAnalysisByKeys($fullAnalysis, $keys, include: true);
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
