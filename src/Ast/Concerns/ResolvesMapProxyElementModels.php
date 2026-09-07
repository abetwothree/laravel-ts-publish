<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;

/**
 * Resolve the element model behind a `->map` proxy receiver.
 *
 * The single home for this: RelationFilterHandler and VariableHandler both need it for an untyped
 * map closure. Requires the host to also use InspectsAstNodes and ResolvesModelRelationTypes.
 *
 * @internal
 */
trait ResolvesMapProxyElementModels
{
    /**
     * Resolve the element model behind a `->map` proxy receiver: a whenLoaded to-many closure
     * parameter, or `$this->relation` itself. A singular relation's bound variable is not a
     * collection and must not match, so it returns null rather than guessing a shape.
     *
     * The binding is never invalidated by a reassignment inside the closure (e.g.
     * `$members = $members->flatMap(...)` before `$members->map(...)`), so a reassigned receiver
     * still resolves against the original relation's element model — an accepted approximation.
     *
     * @return class-string<Model>|null
     */
    protected function resolveMapProxyElementModel(Expr $receiver, AnalysisScope $scope): ?string
    {
        if ($receiver instanceof Variable
            && is_string($receiver->name)
            && isset($scope->varCollectionBindings[$receiver->name])
        ) {
            return $scope->varCollectionBindings[$receiver->name]['modelFqcn'];
        }

        if ($receiver instanceof PropertyFetch
            && $this->isThisPropertyFetch($receiver)
            && $receiver->name instanceof Identifier
        ) {
            $relationInfo = $this->resolveModelRelationTypeInfo($receiver->name->toString(), $scope);

            if (str_ends_with($relationInfo['type'], '[]') && $relationInfo['modelFqcn'] !== null) {
                return $relationInfo['modelFqcn'];
            }
        }

        return null;
    }
}
