<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The resource a ResourceCollection subject collects. Kept apart from InspectsResourceSubject because it needs
 * InspectsResourceCalls, which two of that trait's consumers do not use. Requires the host to also use
 * Analyzers\Concerns\InspectsResourceCalls.
 *
 * @internal
 */
trait ResolvesSingularResourceClass
{
    /**
     * Resolve the singular resource FQCN this ResourceCollection collects.
     * See InspectsResourceCalls::resolveCollectedResourceClass() for the resolution order.
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
