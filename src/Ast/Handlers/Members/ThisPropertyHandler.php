<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers\Members;

use AbeTwoThree\LaravelTsPublish\Analyzers\ResourceAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Models\ResolvesModelRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Resources\InspectsResourceSubject;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Resources\ResolvesEnumPropertyArgTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Docblocks\PropertyDocblockTypeReader;
use AbeTwoThree\LaravelTsPublish\Ast\Members\SubjectPropertyTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverMemberReader;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverMethodReturnResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Resources\ResourceCollections;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;
use ReflectionClass;

/**
 * `$this->property` — resolved against the backing model, attributes before relations, matching
 * Laravel's `Model::__get`. Also carries `extractPropertiesFromArray()`, a plain-array-literal
 * property extractor still used by the analyzer's own merge()/mergeWhen() resolution.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class ThisPropertyHandler implements ExpressionHandler
{
    use InspectsAstNodes;
    use InspectsResourceSubject;
    use ResolvesEnumPropertyArgTypes;
    use ResolvesModelRelationTypes;

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
     * Public: the analyzer's own mergedArrayAnalysis() (merge()/mergeWhen() resolution) calls this
     * directly — the array machinery moved here while that caller stayed on the analyzer.
     *
     * @param  AnalysisScope  $scope  the scope a constant key's `self`, `static` and `parent` are read in
     */
    public function extractPropertiesFromArray(Array_ $array, ExpressionEngine $engine, AnalysisScope $scope, bool $optional = false): ResourceAnalysis
    {
        $analysis = new ResourceAnalysis;

        foreach ($array->items as $item) {
            if ($item->key === null) {
                continue;
            }

            $keyName = $this->resolveKeyName($item->key, $scope);

            if ($keyName === null) {
                continue;
            }

            $result = $engine->resolve($item->value);

            if (JsEmitter::isIndexSignatureKey($keyName)) {
                $analysis->addSignatureEntry($keyName, $result);

                continue;
            }

            $analysis->addProperty($keyName, $result, $optional);
        }

        return $analysis;
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

        // PHP reads the declared JsonResource::$resource before any __get(), so a model member named `resource` is
        // unreachable: the value is its items, the class a proof holds, the backing model or the redeclaration.
        if ($this->isProxyFetch($expr, $scope)) {
            $receivers = resolve(ReceiverClassResolver::class);
            $proxy = $receivers->proxyHeld($scope);
            $items = $proxy === null ? null : resolve(ReceiverMethodReturnResolver::class)->heldItems($proxy, $scope);
            $held = match (true) {
                $items !== null => $items,
                $scope->provenProxy !== null => $this->provenValue($scope->provenProxy, $scope),
                default => $this->proxiedModel($receivers, $scope)
                    ?? resolve(SubjectPropertyTypeResolver::class)->resolve($scope->proxyDeclarer(), $propName, $scope->runtime)
                    ?? $this->declaredHeldValue($receivers, $scope),
            };

            return $held !== null && ValueResult::namesOnlyPublishedModels($held) ? $held : $result;
        }

        $owner = $scope->thisMemberClass($propName, false);

        // PHP reads a declared property before JsonResource::__get() ever forwards to the model.
        if ($scope->modelClass !== null && resolve(SubjectPropertyTypeResolver::class)->declaresOwnProperty($owner, $propName)) {
            $own = resolve(SubjectPropertyTypeResolver::class)->resolve($owner, $propName, $scope->runtime);

            // An abstract or framework model would be emitted as a token nothing imports.
            return $own !== null && ValueResult::namesOnlyPublishedModels($own) ? $own : $result;
        }

        $info = $this->resolveModelAttributeTypeInfo($propName, $scope, $scope->casesAsLiterals);

        if ($info['type'] !== 'unknown') {
            return ValueResult::withAttributeChannels([...$result, 'type' => $info['type']], $info);
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

        $held = $this->heldMemberValue($owner, $propName, $scope);

        if ($held !== null) {
            return $held;
        }

        // Subject mode: with no backing model, `$this->prop` can only mean a property `$this` declares.
        if ($scope->modelClass === null) {
            return resolve(SubjectPropertyTypeResolver::class)->resolve($owner, $propName, $scope->runtime) ?? $result;
        }

        return $result;
    }

    /**
     * The model the proxy is read as whole, as the model's own interface with any null its redeclaration admits; null
     * when it is read as no model, which publishes as the subject declares it.
     *
     * @return ValueExpressionResult|null
     */
    private function proxiedModel(ReceiverClassResolver $receivers, AnalysisScope $scope): ?array
    {
        $model = $receivers->proxyModel($scope);

        if ($model === null) {
            return null;
        }

        $type = class_basename($model);

        return [
            ...ValueResult::unknown(),
            'type' => $receivers->redeclaresNullableProxy($scope) ? TsTypeString::withNullArm($type) : $type,
            'modelFqcn' => $model,
        ];
    }

    /**
     * The proxy read whole where only a tag declares what it holds, as `@extends JsonResource<PostStats>` does: those
     * classes other than a model, with any null the tag admits; null where none is declared.
     *
     * @return ValueExpressionResult|null
     */
    private function declaredHeldValue(ReceiverClassResolver $receivers, AnalysisScope $scope): ?array
    {
        return $scope->declaredHeld === null
            ? null
            : $this->provenValue($scope->declaredHeld, $scope, $receivers->redeclaresNullableProxy($scope));
    }

    /**
     * `$this->name` where the subject declares no such property and the proxy holds only classes other than a model,
     * read off them as JsonResource::__get() forwards it; null otherwise.
     *
     * @param  ReflectionClass<object>  $owner
     * @return ValueExpressionResult|null
     */
    private function heldMemberValue(ReflectionClass $owner, string $name, AnalysisScope $scope): ?array
    {
        if ($scope->modelClass !== null || ! $scope->hasResourceProxy() || $owner->hasProperty($name)) {
            return null;
        }

        $held = resolve(ReceiverClassResolver::class)->proxyHeld($scope);
        $holdsModel = $held !== null && array_any($held->classes, fn (string $class): bool => is_a($class, Model::class, true));

        return $held === null || $held->collectedItems || $holdsModel ? null : ReceiverMemberReader::value($held, $name, $scope);
    }

    /**
     * The classes an `instanceof` proof holds the proxy to, as their published types, with a null arm where it admits
     * null; null when one has none.
     *
     * @param  non-empty-list<class-string>  $proven
     * @return ValueExpressionResult|null
     */
    private function provenValue(array $proven, AnalysisScope $scope, bool $nullable = false): ?array
    {
        $declared = implode('|', array_map(fn (string $class): string => '\\'.$class, $proven)).($nullable ? '|null' : '');

        return resolve(PropertyDocblockTypeReader::class)->readDeclared($declared, $scope->proxyDeclarer());
    }

    /**
     * Analyze $this->collection in a ResourceCollection: the runtime class's collected list, keyed as that class keeps
     * keys, since an inherited body runs as the class Laravel builds.
     *
     * @return ValueExpressionResult
     */
    private function analyzeCollectionProperty(AnalysisScope $scope): array
    {
        return ResourceCollections::listValue($scope->collectedElement, ! $scope->reindexesNumericKeys);
    }
}
