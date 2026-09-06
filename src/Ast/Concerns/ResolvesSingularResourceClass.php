<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The resource a ResourceCollection subject collects.
 *
 * Kept apart from InspectsResourceSubject because this one needs InspectsAstNodes, which two of
 * that trait's consumers do not use. Requires the host to also use InspectsAstNodes.
 *
 * @internal
 */
trait ResolvesSingularResourceClass
{
    /**
     * Resolve the singular resource FQCN this ResourceCollection collects.
     * See InspectsAstNodes::resolveCollectedResourceClass() for the resolution order.
     *
     * @return class-string<JsonResource>|null
     */
    protected function resolveSingularResourceClass(AnalysisScope $scope): ?string
    {
        /** @var class-string $ownFqcn */
        $ownFqcn = $scope->subjectReflection->getName();

        return $this->resolveCollectedResourceClass($ownFqcn);
    }
}
