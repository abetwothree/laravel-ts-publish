<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\ResourceCollections;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The resource a ResourceCollection subject collects.
 *
 * @internal
 */
trait ResolvesSingularResourceClass
{
    /**
     * Resolve the singular resource FQCN this ResourceCollection collects.
     * See ResourceCollections::collectedResource() for the resolution order.
     *
     * @return class-string<JsonResource>|null
     */
    protected function resolveSingularResourceClass(AnalysisScope $scope): ?string
    {
        return ResourceCollections::collectedResource($scope->subjectReflection->getName());
    }
}
