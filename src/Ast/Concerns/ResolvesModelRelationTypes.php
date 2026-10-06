<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

/**
 * Resolve a `$this->{name}` property as a relation on the scope's backing model.
 *
 * The single home for this: five handlers plus ResourceAstAnalyzer carried byte-identical copies
 * before this trait. Any other trait the consuming class uses must not declare these names — PHP
 * collides on method name alone and PHPStan compiles the resulting fatal cleanly.
 *
 * @internal
 */
trait ResolvesModelRelationTypes
{
    /**
     * Resolve a `$this->{name}` property as a model relation, in ModelAttributeResolver::resolveRelation()'s
     * {type, modelFqcn, morphFqcns} shape — a to-many relation's type ends in '[]'.
     *
     * @return array{type: string, modelFqcn: class-string<Model>|null, morphFqcns: list<class-string>}
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
}
