<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Concerns\ResolvesClassNames;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Facts about the subject class under analysis, read straight off the scope: the class its
 * `$resource` wraps, and whether it is a ResourceCollection.
 *
 * The single home for both.
 *
 * @internal
 */
trait InspectsResourceSubject
{
    use ResolvesClassNames;

    /**
     * Resolve the wrapped class for this resource, falling back to the instanceof guard clause hint.
     *
     * @return class-string|null
     */
    protected function resolveWrappedClass(AnalysisScope $scope): ?string
    {
        return $this->resolveClassOnProperty($scope->subjectReflection) ?? $scope->instanceOfWrappedClass;
    }

    /**
     * Determine whether the analyzed resource is a ResourceCollection subclass.
     */
    protected function isResourceCollection(AnalysisScope $scope): bool
    {
        return $scope->subjectReflection->isSubclassOf(ResourceCollection::class);
    }
}
