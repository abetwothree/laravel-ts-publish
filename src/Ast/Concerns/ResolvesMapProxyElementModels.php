<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;

/**
 * Resolve the element model behind a `->map` proxy receiver, the one home RelationFilterHandler and VariableHandler
 * share for an untyped map closure. Requires the host to also use Ast\Concerns\InspectsAstNodes and
 * ResolvesModelRelationTypes.
 *
 * @internal
 */
trait ResolvesMapProxyElementModels
{
    /**
     * Resolve the element model behind a `->map` proxy receiver: a whenLoaded to-many closure parameter, or
     * `$this->relation`, also as `$this->resource->relation`; null for a singular relation's variable, which is no
     * collection, or for a variable read past the write that ended its parameter binding.
     *
     * @return class-string<Model>|null
     */
    protected function resolveMapProxyElementModel(Expr $receiver, AnalysisScope $scope): ?string
    {
        if ($receiver instanceof Variable
            && is_string($receiver->name)
            && isset($scope->varCollectionBindings[$receiver->name])
            && $scope->holdsBinding($receiver)
        ) {
            return $scope->varCollectionBindings[$receiver->name]['modelFqcn'];
        }

        if ($receiver instanceof PropertyFetch && $this->isModelMemberFetch($receiver, $scope) && $receiver->name instanceof Identifier) {
            $relationInfo = $this->resolveModelRelationTypeInfo($receiver->name->toString(), $scope);

            if (str_ends_with($relationInfo['type'], '[]') && $relationInfo['modelFqcn'] !== null) {
                return $relationInfo['modelFqcn'];
            }
        }

        return null;
    }
}
