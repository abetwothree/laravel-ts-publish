<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\DecomposesPropertyChains;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsResourceSubject;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesEnumPropertyArgTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\SubjectPropertyTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;
use ReflectionEnum;

/**
 * Property chains rooted at `$this` — `$this->user?->name`, `$this->resource->value`,
 * `$this->resource->user->role` — traversing relation steps until the final property resolves.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class PropertyChainHandler implements ExpressionHandler
{
    use DecomposesPropertyChains;
    use InspectsAstNodes;
    use InspectsResourceSubject;
    use ResolvesEnumPropertyArgTypes;

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [NullsafePropertyFetch::class, PropertyFetch::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if ($expr instanceof NullsafePropertyFetch) {
            $info = $this->analyzePropertyChain($expr, $scope);

            return TsTypeString::isUnknownOnly($info['type']) ? null : $info;
        }

        // $this->resource->subProp — e.g. ->name / ->value on a backed enum, or a column of a wrapped model
        if ($expr instanceof PropertyFetch && $this->isResourceFetch($expr->var)) {
            $info = $this->analyzeWrappedEnumResourceProperty($expr, $scope);

            if ($info['type'] === 'unknown') {
                $info = $this->analyzeWrappedModelResourceProperty($expr, $scope);
            }

            if ($info['type'] === 'unknown') {
                $info = $this->analyzePropertyChain($expr, $scope);
            }

            return TsTypeString::isUnknownOnly($info['type']) ? null : $info;
        }

        // Any other chain rooted at `$this`: `$this->author->name`, or 3+ deep as `$this->resource->user->role`.
        if ($expr instanceof PropertyFetch) {
            $info = $this->analyzePropertyChain($expr, $scope);

            if ($info['type'] !== 'unknown') {
                return $info;
            }
        }

        return null;
    }

    /**
     * Analyze a property-fetch chain rooted at `$this`, traversing relation steps until the final
     * property resolves. Handles `->` and `?->` in any mix; any `?->` step appends `| null`.
     *
     * The chain starts at $modelClass, inside a relation's closure too, since `$this` is still the resource there.
     *
     * @return ValueExpressionResult
     */
    private function analyzePropertyChain(Expr $expr, AnalysisScope $scope): array
    {
        $chain = $this->decomposePropertyChain($expr);

        if ($chain === null || $chain === []) {
            return ValueResult::unknown();
        }

        // A chain rooted at a property the subject declares is not a model walk. Declining hands it to
        // ReceiverPropertyFetchHandler, which resolves it from that property's own class.
        if ($scope->modelClass !== null
            && resolve(SubjectPropertyTypeResolver::class)->declaresOwnProperty($scope->subjectReflection, $chain[0]['name'])
        ) {
            return ValueResult::unknown();
        }

        /** @var class-string<Model>|null $currentModel */
        $currentModel = $scope->modelClass;

        // Subject mode: no backing model, so the chain's first segment must be the subject's own
        // property, and the relation/attribute walk below starts from the model it is typed as.
        if ($currentModel === null) {
            $currentModel = $this->resolveSubjectRootModel($chain[0]['name'], $scope);

            if ($currentModel === null) {
                return ValueResult::unknown();
            }

            array_shift($chain);

            if ($chain === []) {
                return ValueResult::unknown();
            }
        }

        $resolver = resolve(ModelAttributeResolver::class);

        // `$this->resource` is the resource's own model.
        if ($chain[0]['name'] === 'resource'
            && $scope->modelClass !== null
            && $resolver->resolveRelation($scope->modelClass, 'resource')['type'] === 'unknown'
        ) {
            array_shift($chain);
        }

        if ($chain === []) {
            return ValueResult::unknown();
        }

        $hasNullable = array_any($chain, fn (array $step): bool => $step['nullable']);

        $count = count($chain);

        for ($i = 0; $i < $count - 1; $i++) {
            $currentModel = $this->stepModel($currentModel, $chain[$i]['name'], $scope);

            if ($currentModel === null) {
                return ValueResult::unknown();
            }
        }

        $lastStep = $chain[$count - 1];
        $tsInfo = $resolver->resolveAttribute($currentModel, $lastStep['name'], $scope->carriesImports);

        if ($tsInfo['type'] === 'unknown') {
            // The final step may itself be a relation (e.g. $this->user?->profile).
            $relationInfo = $resolver->resolveRelation($currentModel, $lastStep['name']);

            if ($relationInfo['type'] === 'unknown') {
                return ValueResult::unknown();
            }

            $type = $hasNullable && ! str_ends_with($relationInfo['type'], ' | null')
                ? $relationInfo['type'].' | null'
                : $relationInfo['type'];

            /** @var ValueExpressionResult $result */
            $result = ['type' => $type, 'optional' => false];

            if ($relationInfo['modelFqcn'] !== null) {
                $result['modelFqcn'] = $relationInfo['modelFqcn'];
            }

            if ($relationInfo['morphFqcns'] !== []) {
                $result['embeddedModelFqcns'] = $relationInfo['morphFqcns'];
            }

            return $result;
        }

        $type = $hasNullable && ! str_ends_with($tsInfo['type'], ' | null')
            ? $tsInfo['type'].' | null'
            : $tsInfo['type'];

        return ValueResult::withAttributeChannels(['type' => $type, 'optional' => false], $tsInfo);
    }

    /**
     * The one model a chain step holds: a to-one relation's model, or an accessor typed as a single model.
     *
     * A to-many relation holds a collection, which has none of the model's properties, so it declines.
     *
     * @param  class-string<Model>  $model
     * @return class-string<Model>|null
     */
    private function stepModel(string $model, string $name, AnalysisScope $scope): ?string
    {
        $resolver = resolve(ModelAttributeResolver::class);
        $relation = $resolver->resolveRelation($model, $name);

        if ($relation['type'] !== 'unknown') {
            return $relation['modelFqcn'] !== null && ! str_contains($relation['type'], '[]')
                ? $relation['modelFqcn']
                : null;
        }

        $attribute = $resolver->resolveAttribute($model, $name, $scope->carriesImports);
        $held = $attribute['classFqcns'];

        return count($held) === 1
            && is_a($held[0], Model::class, true)
            && preg_match('/^\w+( \| null)?$/', $attribute['type']) === 1
            ? $held[0]
            : null;
    }

    /**
     * Resolve a model-less subject's own property to the model it is typed as, or null when it is
     * typed as anything else — `@var` docblock first, native declared type second.
     *
     * @return class-string<Model>|null
     */
    private function resolveSubjectRootModel(string $name, AnalysisScope $scope): ?string
    {
        $result = resolve(SubjectPropertyTypeResolver::class)->resolve($scope->subjectReflection, $name);
        $modelFqcn = $result['modelFqcn'] ?? null;

        return $modelFqcn !== null && is_a($modelFqcn, Model::class, true) ? $modelFqcn : null;
    }

    /**
     * Analyze `$this->resource->subProp` where `$this->resource` holds a wrapped enum.
     *
     * PHP enum universals: `->name` is always string, `->value` follows the enum's backing type.
     *
     * @return ValueExpressionResult
     */
    private function analyzeWrappedEnumResourceProperty(PropertyFetch $expr, AnalysisScope $scope): array
    {
        $result = ValueResult::unknown();
        $innerProp = $expr->name instanceof Identifier ? $expr->name->toString() : null;

        if ($innerProp === null) {
            return $result; // @codeCoverageIgnore
        }

        // Guarded on the wrapped type actually being an enum: without it, a model-backed
        // `$this->resource->column` would silently receive 'string' instead of its column type.
        $wrappedClass = $this->resolveWrappedClass($scope);

        if ($wrappedClass === null || ! enum_exists($wrappedClass)) {
            return $result;
        }

        if ($innerProp === 'name') {
            return [
                ...$result,
                'type' => 'string',
            ];
        }

        if ($innerProp === 'value') {
            return [
                ...$result,
                'type' => $this->resolveEnumValueBackingType($scope),
            ];
        }

        return $result;
    }

    /**
     * Analyze `$this->resource->subProp` where `$this->resource` is a wrapped model resource
     * (i.e. has a `@var ModelType|null` docblock on `$resource`).
     *
     * @return ValueExpressionResult
     */
    private function analyzeWrappedModelResourceProperty(PropertyFetch $expr, AnalysisScope $scope): array
    {
        $result = ValueResult::unknown();
        $innerProp = $expr->name instanceof Identifier ? $expr->name->toString() : null;

        if ($innerProp === null) {
            return $result; // @codeCoverageIgnore
        }

        $wrappedClass = $this->resolveWrappedClass($scope);

        if ($wrappedClass === null || ! class_exists($wrappedClass)) {
            return $result;
        }

        $info = $this->resolveModelAttributeTypeInfo($innerProp, $scope);

        if ($info['type'] !== 'unknown') {
            return ValueResult::withAttributeChannels(['type' => $info['type'], 'optional' => false], $info);
        }

        return $result; // @codeCoverageIgnore
    }

    /**
     * Determine the TypeScript type for a backed enum's `->value` property from its backing type.
     */
    private function resolveEnumValueBackingType(AnalysisScope $scope): string
    {
        $wrappedClass = $this->resolveWrappedClass($scope);

        if ($wrappedClass !== null && enum_exists($wrappedClass)) {
            $r = new ReflectionEnum($wrappedClass);
            $backingType = $r->getBackingType();

            if ($backingType !== null) {
                return $backingType->getName() === 'string' ? 'string' : 'number';
            }
        }

        return 'string | number';
    }
}
