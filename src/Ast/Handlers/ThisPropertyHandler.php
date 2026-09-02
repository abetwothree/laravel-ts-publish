<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\ChecksPreserveKeys;
use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\DispatchesFqcnResults;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsResourceSubject;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesEnumPropertyArgTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesModelRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesSingularResourceClass;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\SubjectPropertyTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;

/**
 * `$this->property` — resolved against the backing model, attributes before relations, matching
 * Laravel's `Model::__get`. Also carries `extractPropertiesFromArray()`, a plain-array-literal
 * property extractor still used by the analyzer's own merge()/mergeWhen() resolution.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type ResourcePropertyInfoList from MethodAnalysis
 * @phpstan-import-type ClassMapType from MethodAnalysis
 * @phpstan-import-type ImportMapType from MethodAnalysis
 * @phpstan-import-type InlineEnumFqcnsMap from MethodAnalysis
 * @phpstan-import-type InlineModelFqcnsMap from MethodAnalysis
 * @phpstan-import-type MultiEnumFqcnsMap from MethodAnalysis
 */
final class ThisPropertyHandler implements ExpressionHandler
{
    use ChecksPreserveKeys;
    use DispatchesFqcnResults;
    use InspectsAstNodes;
    use InspectsResourceSubject;
    use ResolvesEnumPropertyArgTypes;
    use ResolvesModelRelationTypes;
    use ResolvesSingularResourceClass;

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [PropertyFetch::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if ($this->isThisPropertyFetch($expr)) {
            return $this->analyzeThisProperty($expr, $scope);
        }

        return null;
    }

    /**
     * Extract properties and FQCNs from an array expression, e.g. for mergeWhen's second argument.
     *
     * Public: the analyzer's own resolveArrayOrClosureToProperties() (merge()/mergeWhen() resolution)
     * calls this directly — the array machinery moved here while that caller stayed on the analyzer.
     */
    public function extractPropertiesFromArray(Array_ $array, ExpressionEngine $engine, bool $optional = false): ResourceAnalysis
    {
        /** @var ResourcePropertyInfoList $properties */
        $properties = [];
        /** @var ClassMapType $enumResources */
        $enumResources = [];
        /** @var ClassMapType $nestedResources */
        $nestedResources = [];
        /** @var ClassMapType $directEnumFqcns */
        $directEnumFqcns = [];
        /** @var ClassMapType $modelFqcns */
        $modelFqcns = [];
        /** @var ImportMapType $customImports */
        $customImports = [];
        /** @var InlineEnumFqcnsMap $inlineEnumFqcns */
        $inlineEnumFqcns = [];
        /** @var InlineModelFqcnsMap $inlineModelFqcns */
        $inlineModelFqcns = [];
        /** @var MultiEnumFqcnsMap $multiEnumResourceFqcns */
        $multiEnumResourceFqcns = [];
        /** @var InlineEnumFqcnsMap $inlineEnumResourceFqcns */
        $inlineEnumResourceFqcns = [];

        foreach ($array->items as $item) {
            if ($item->key === null) {
                continue;
            }

            $keyName = $this->resolveKeyName($item->key);

            if ($keyName === null) {
                continue;
            }

            $result = $engine->resolve($item->value);

            $properties[] = [
                'name' => $keyName,
                'type' => $result['type'],
                'optional' => $optional || $result['optional'],
                'description' => '',
            ];

            $this->dispatchFqcnResults($keyName, $result, $enumResources, $directEnumFqcns, $nestedResources, $modelFqcns, $multiEnumResourceFqcns);

            foreach ($result['embeddedEnumFqcns'] ?? [] as $fqcn) {
                $inlineEnumFqcns[$keyName][] = $fqcn;
            }

            foreach ($result['embeddedEnumResourceFqcns'] ?? [] as $fqcn) {
                $inlineEnumResourceFqcns[$keyName][] = $fqcn;
            }

            foreach ($result['embeddedModelFqcns'] ?? [] as $fqcn) {
                $inlineModelFqcns[$keyName][] = $fqcn;
            }

            foreach ($result['customImports'] ?? [] as $path => $types) {
                $customImports[$path] = [...($customImports[$path] ?? []), ...$types];
            }
        }

        return new ResourceAnalysis(
            $properties,
            $enumResources,
            $nestedResources,
            customImports: $customImports,
            directEnumFqcns: $directEnumFqcns,
            modelFqcns: $modelFqcns,
            inlineEnumFqcns: $inlineEnumFqcns,
            inlineModelFqcns: $inlineModelFqcns,
            multiEnumResourceFqcns: $multiEnumResourceFqcns,
            inlineEnumResourceFqcns: $inlineEnumResourceFqcns,
        );
    }

    /**
     * Analyze $this->property — resolve the type from the backing model, attributes before relations
     * (matching Laravel's Model::__get).
     *
     * @return ValueExpressionResult
     */
    private function analyzeThisProperty(Expr $expr, AnalysisScope $scope): array
    {
        $result = ValueResult::unknown();

        /** @var PropertyFetch $expr */
        $propName = $expr->name instanceof Identifier ? $expr->name->toString() : null;

        if ($propName === null) {
            return $result; // @codeCoverageIgnore
        }

        if ($propName === 'collection' && $this->isResourceCollection($scope)) {
            return $this->analyzeCollectionProperty($scope);
        }

        $info = $this->resolveModelAttributeTypeInfo($propName, $scope);

        if ($info['type'] !== 'unknown') {
            $result = [
                ...$result,
                'type' => $info['type'],
            ];

            if ($info['enumFqcn'] !== null) {
                $result['directEnumFqcn'] = $info['enumFqcn'];
            }

            // A single-FQCN accessor needs no per-occurrence disambiguation; only a genuine union
            // needs its FQCNs threaded out here for aliasPropertyType() to consume per occurrence.
            if (count($info['classFqcns']) > 1) {
                $result['embeddedModelFqcns'] = $info['classFqcns'];
            } elseif (count($info['classFqcns']) === 1) {
                $result['modelFqcn'] = $info['classFqcns'][0];
            }

            return $result;
        }

        $relationInfo = $this->resolveModelRelationTypeInfo($propName, $scope);

        if ($relationInfo['type'] !== 'unknown') {
            $result = [
                ...$result,
                'type' => $relationInfo['type'],
            ];

            if ($relationInfo['modelFqcn'] !== null) {
                $result['modelFqcn'] = $relationInfo['modelFqcn'];
            }

            if ($relationInfo['morphFqcns'] !== []) {
                $result['embeddedModelFqcns'] = $relationInfo['morphFqcns'];
            }

            return $result;
        }

        // Subject mode: with no backing model, `$this->prop` can only mean the subject's own property.
        if ($scope->modelClass === null) {
            return resolve(SubjectPropertyTypeResolver::class)->resolve($scope->subjectReflection, $propName) ?? $result;
        }

        return $result;
    }

    /**
     * Analyze $this->collection in a ResourceCollection: the singular resource type as an array,
     * or a keyed record when the collection preserves keys.
     *
     * @return ValueExpressionResult
     */
    private function analyzeCollectionProperty(AnalysisScope $scope): array
    {
        $result = ValueResult::unknown();
        $singular = $this->resolveSingularResourceClass($scope);

        if ($singular === null) {
            return $result;
        }

        return [
            ...$result,
            'type' => $this->wrapCollectionElementType(LaravelTsPublish::resourceTypeName($singular), $scope->subjectReflection),
            'resourceFqcn' => $singular,
        ];
    }
}
