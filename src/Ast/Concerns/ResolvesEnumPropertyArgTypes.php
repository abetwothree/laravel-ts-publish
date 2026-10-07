<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

/**
 * Resolve an enum type — or the model-attribute type backing one — from a property-fetch argument, a local, or the one
 * enum a resolved payload holds, for both resource-construction shapes: `EnumResource::make()`/`::collection()` and
 * `new EnumResource(...)`.
 * Requires the host to also `use Ast\Concerns\InspectsAstNodes` (for `isThisPropertyFetch()`).
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type TypesImportMap from Datable
 *
 * @phpstan-type AttributeTypeInfo = array{
 *      type: string,
 *      enumFqcn: class-string|null,
 *      enumFqcns: list<class-string>,
 *      classFqcns: list<class-string>,
 *      classTokenFqcns?: list<class-string>,
 *      customImports: TypesImportMap
 * }
 *
 * @internal
 */
trait ResolvesEnumPropertyArgTypes
{
    /**
     * Resolve an enum type from a property-fetch expression (shared by EnumResource::make and new EnumResource).
     *
     * Handles `$this->property` against the resource's own model, and `$variable->property` against
     * `$closureRelationModelClass` inside a whenLoaded() closure.
     *
     * @return ValueExpressionResult|null
     */
    protected function resolveEnumFromPropertyArg(Expr $argExpr, AnalysisScope $scope): ?array
    {
        $result = ValueResult::unknown();

        if (! $this->isThisPropertyFetch($argExpr)) {
            // A bare $variable may be a closure parameter bound to $this->prop by a when() condition, or a local
            // assigned once. Only the local takes VariableHandler's guard against a cyclic binding: it is keyed by
            // name, and a parameter, never bound to a variable, may shadow an outer local that is mid-resolution.
            if ($argExpr instanceof Variable && is_string($argExpr->name)) {
                $name = $argExpr->name;

                if (isset($scope->closureParamExprBindings[$name])) {
                    return $this->resolveEnumFromPropertyArg($scope->closureParamExprBindings[$name], $scope);
                }

                if (isset($scope->localVarBindings[$name]) && ! isset($scope->resolvingLocalVars[$name])) {
                    $scope->resolvingLocalVars[$name] = true;

                    try {
                        return $this->resolveEnumFromPropertyArg($scope->localVarBindings[$name], $scope);
                    } finally {
                        unset($scope->resolvingLocalVars[$name]);
                    }
                }
            }

            // Handle $variable->property inside a whenLoaded closure.
            if (
                $argExpr instanceof PropertyFetch
                && $argExpr->var instanceof Variable
                && $argExpr->name instanceof Identifier
                && $scope->closureRelationModelClass !== null
            ) {
                $propName = $argExpr->name->toString();
                $tsInfo = resolve(ModelAttributeResolver::class)->resolveAttribute($scope->closureRelationModelClass, $propName);

                /** @var class-string|null $enumFqcn */
                $enumFqcn = $tsInfo['enumFqcns'][0] ?? null;

                if ($enumFqcn === null) {
                    return null;
                }

                // toTsType() on the FQCN directly yields the pure enum type, without the nullable
                // suffix appendNullable() adds from the DB column definition.
                $enumTsInfo = LaravelTsPublish::toTsType($enumFqcn);

                return [
                    ...$result,
                    'type' => $enumTsInfo['type'],
                    'enumFqcn' => $enumFqcn,
                ];
            }

            // `$this->resource->property` is equivalent to `$this->property`, since $this->resource
            // is the underlying model instance.
            if (
                $argExpr instanceof PropertyFetch
                && $this->isResourceFetch($argExpr->var)
                && $argExpr->name instanceof Identifier
            ) {
                $propName = $argExpr->name->toString();
                $info = $this->resolveModelAttributeTypeInfo($propName, $scope);

                if ($info['enumFqcn'] === null) {
                    return null;
                }

                return [
                    ...$result,
                    'type' => $info['type'],
                    'enumFqcn' => $info['enumFqcn'],
                ];
            }

            // Enum::staticMethod(...) or Enum::Case — resolved from the class name alone. parseAndResolveAst()
            // runs a NameResolver, so ->class is already the FQCN.
            $enumClassName = null;

            if ($argExpr instanceof StaticCall && $argExpr->class instanceof Name) {
                $enumClassName = $argExpr->class->toString();
            } elseif ($argExpr instanceof ClassConstFetch && $argExpr->class instanceof Name) {
                $enumClassName = $argExpr->class->toString();
            }

            if ($enumClassName !== null && enum_exists($enumClassName)) {
                $enumTsInfo = LaravelTsPublish::toTsType($enumClassName);

                return [
                    ...$result,
                    'type' => $enumTsInfo['type'],
                    'enumFqcn' => $enumClassName,
                ];
            }

            return null;
        }

        /** @var PropertyFetch $argExpr */
        $propName = $argExpr->name instanceof Identifier ? $argExpr->name->toString() : null;

        if ($propName === null) {
            return null; // @codeCoverageIgnore
        }

        $info = $this->resolveModelAttributeTypeInfo($propName, $scope);

        if ($info['enumFqcn'] === null) {
            return null;
        }

        return [
            ...$result,
            'type' => $info['type'],
            'enumFqcn' => $info['enumFqcn'],
        ];
    }

    /**
     * The one enum a payload those spellings cannot read resolves to, such as a helper's return or a coalesce: its
     * type, `| null` kept, on the wrap's `enumFqcn`. A collection's payload may also be a list of that enum.
     *
     * @return ValueExpressionResult|null null for several enums, or a type that holds anything besides the enum
     */
    protected function resolveEnumFromResolvedPayload(Expr $payload, ExpressionEngine $engine, bool $allowsList = false): ?array
    {
        $resolved = $engine->resolve($payload);
        // A union of enum reads, such as a `??` over a case, carries its one enum on the multi-entry channel.
        $fqcns = array_values(array_unique([
            ...(isset($resolved['directEnumFqcn']) ? [$resolved['directEnumFqcn']] : []),
            ...($resolved['embeddedEnumFqcns'] ?? []),
        ]));

        if (count($fqcns) !== 1) {
            return null;
        }

        $enumType = LaravelTsPublish::toTsType($fqcns[0])['type'];
        $held = ValueResult::stripNullArm($resolved['type']);

        if ($held !== $enumType && (! $allowsList || $held !== $enumType.'[]')) {
            return null;
        }

        return [...ValueResult::unknown(), 'type' => $resolved['type'], 'enumFqcn' => $fqcns[0]];
    }

    /**
     * Resolve the TypeScript type, optional enum FQCN, class FQCNs and `#[TsType]` imports for a model attribute.
     *
     * Bypasses ResolvesModelTypes's cached-property gate, which this per-call handler never
     * populates — calls the ModelAttributeResolver singleton directly instead; it caches per FQCN.
     *
     * @return AttributeTypeInfo
     */
    protected function resolveModelAttributeTypeInfo(string $attributeName, AnalysisScope $scope): array
    {
        if ($scope->modelClass === null) {
            return ['type' => 'unknown', 'enumFqcn' => null, 'enumFqcns' => [], 'classFqcns' => [], 'customImports' => []];
        }

        $tsInfo = resolve(ModelAttributeResolver::class)->resolveAttribute($scope->modelClass, $attributeName, $scope->carriesImports);

        /** @var class-string|null $enumFqcn */
        $enumFqcn = $tsInfo['enumFqcns'][0] ?? null;

        return [
            'type' => $tsInfo['type'],
            'enumFqcn' => $enumFqcn,
            'enumFqcns' => $tsInfo['enumFqcns'],
            'classFqcns' => $tsInfo['classFqcns'],
            // Present only where the type spells one name for two classes; see TypeScriptTypeInfo.
            ...(isset($tsInfo['classTokenFqcns']) ? ['classTokenFqcns' => $tsInfo['classTokenFqcns']] : []),
            'customImports' => $tsInfo['customImports'],
        ];
    }
}
