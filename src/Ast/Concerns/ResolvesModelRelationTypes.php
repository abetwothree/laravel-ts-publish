<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Illuminate\Support\Facades\Config;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Foreach_;

/**
 * Resolve a `$this->{name}` property as a relation on the scope's backing model.
 *
 * The single home for this: five handlers plus ResourceAstAnalyzer carried byte-identical copies
 * before this trait. Any other trait the consuming class uses must not declare these names — PHP
 * collides on method name alone and PHPStan compiles the resulting fatal cleanly.
 *
 * @phpstan-import-type RelationTypeInfo from ModelAttributeResolver
 *
 * @internal
 */
trait ResolvesModelRelationTypes
{
    /**
     * Whether an expression is a `$this->prop` property fetch, whichever property it names.
     */
    abstract protected function isThisPropertyFetch(Expr $expr): bool;

    /**
     * Resolve a `$this->{name}` property as a model relation, in ModelAttributeResolver::resolveRelation()'s
     * {type, modelFqcn, morphFqcns} shape — a to-many relation's type ends in '[]'.
     *
     * @return RelationTypeInfo
     */
    protected function resolveModelRelationTypeInfo(string $name, AnalysisScope $scope): array
    {
        if ($scope->modelClass === null) {
            return ['type' => 'unknown', 'modelFqcn' => null, 'morphFqcns' => []];
        }

        return resolve(ModelAttributeResolver::class)->resolveRelation($scope->modelClass, $name);
    }

    /**
     * Whether a `$this->{name}` relation can be loaded as null, by ModelAttributeResolver::relationLoadsNull()'s
     * rule; a scope with no backing model answers as for a relation the model does not declare.
     */
    protected function relationLoadsNull(string $name, AnalysisScope $scope): ?bool
    {
        if ($scope->modelClass === null) {
            return Config::boolean('ts-publish.models.nullable_relations') ? null : false;
        }

        return resolve(ModelAttributeResolver::class)->relationLoadsNull($scope->modelClass, $name);
    }

    /**
     * Bind a top-level `foreach ($this->{manyRelation} as $item)`'s value variable to the relation's element model, for
     * the whole body, as seedBody() binds a local.
     *
     * @param  array<Stmt>  $stmts
     */
    protected function bindForeachLoopVariables(array $stmts, AnalysisScope $scope): void
    {
        foreach ($stmts as $stmt) {
            if (! $stmt instanceof Foreach_
                || ! $stmt->valueVar instanceof Variable
                || ! is_string($stmt->valueVar->name)
                || ! $stmt->expr instanceof PropertyFetch
                || ! $this->isThisPropertyFetch($stmt->expr)
                || ! $stmt->expr->name instanceof Identifier
            ) {
                continue;
            }

            $relationInfo = $this->resolveModelRelationTypeInfo($stmt->expr->name->toString(), $scope);

            if (str_ends_with($relationInfo['type'], '[]') && $relationInfo['modelFqcn'] !== null) {
                $scope->varModelBindings[$stmt->valueVar->name] = $relationInfo['modelFqcn'];
            }
        }
    }
}
