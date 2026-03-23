<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers\Concerns;

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;

/**
 * Handles $this->only([...]), $this->except([...]) and other attribute filter
 * patterns in JsonResource toArray() methods.
 *
 * @phpstan-import-type ResourcePropertyInfoList from ResourceAnalysis
 * @phpstan-import-type ClassMapType from ResourceAnalysis
 */
trait FiltersModelAttributes
{
    /**
     * The attribute filter methods supported by the analyzer.
     *
     * @return list<string>
     */
    protected function supportedAttributeFilters(): array
    {
        return ['only', 'except'];
    }

    /**
     * Route a $this->only([...]) or $this->except([...]) call to the appropriate handler.
     */
    protected function analyzeThisAttributeFilter(MethodCall $call): ?ResourceAnalysis
    {
        if (! ($call->var instanceof Variable && $call->var->name === 'this' && $call->name instanceof Identifier)) {
            return null; // @codeCoverageIgnore
        }

        $methodName = $call->name->toString();

        if (! in_array($methodName, $this->supportedAttributeFilters(), true)) {
            return null; // @codeCoverageIgnore
        }

        $keys = $this->extractFilterKeys($call);

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
     * Extract string keys from the first Array_ argument of a filter method call.
     *
     * @return list<string>|null
     */
    protected function extractFilterKeys(MethodCall $call): ?array
    {
        $args = $call->getArgs();

        if (count($args) < 1 || ! $args[0]->value instanceof Array_) {
            return null;
        }

        /** @var list<string> $keys */
        $keys = [];

        foreach ($args[0]->value->items as $arrayItem) {
            if ($arrayItem->value instanceof String_) {
                $keys[] = $arrayItem->value->value;
            }
        }

        return $keys;
    }

    /**
     * Analyze $this->only([...]) — include only the listed model attributes.
     *
     * @param  list<string>  $keys
     */
    protected function analyzeOnlyFilter(array $keys): ?ResourceAnalysis
    {
        $fullAnalysis = $this->buildModelDelegatedAnalysis();

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

        return new ResourceAnalysis(
            properties: $filteredProperties,
            directEnumFqcns: $filteredEnumFqcns,
        );
    }
}
