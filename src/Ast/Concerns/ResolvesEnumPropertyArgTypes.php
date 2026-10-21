<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
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
 * `new EnumResource(...)`. A scope that carries no import reads neither a local nor a resolved payload: the body
 * fallback would drop its whole shape for the enum either names.
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
 *      customImports: TypesImportMap,
 *      keepsKeys?: bool
 * }
 *
 * @internal
 */
trait ResolvesEnumPropertyArgTypes
{
    /**
     * Resolve an enum type from a property-fetch expression (shared by EnumResource::make and new EnumResource).
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

                if (isset($scope->closureParamExprBindings[$name]) && $scope->holdsBinding($argExpr)) {
                    return $this->resolveEnumFromPropertyArg($scope->closureParamExprBindings[$name], $scope);
                }

                $local = $scope->literalLocalBindings()[$name] ?? null;

                if ($scope->carriesImports && $local !== null && ! isset($scope->resolvingLocalVars[$name])) {
                    $scope->resolvingLocalVars[$name] = true;

                    try {
                        return $this->resolveEnumFromPropertyArg($local, $scope);
                    } finally {
                        unset($scope->resolvingLocalVars[$name]);
                    }
                }
            }

            // `$variable->property` inside a whenLoaded() or map() closure, on the model the variable is bound to.
            if (
                $scope->closureRelationModelClass !== null
                && $argExpr instanceof PropertyFetch
                && $argExpr->var instanceof Variable
                && is_string($argExpr->var->name)
                && $argExpr->name instanceof Identifier
                && $scope->holdsBinding($argExpr->var)
                && isset($scope->varModelBindings[$argExpr->var->name])
            ) {
                $propName = $argExpr->name->toString();
                $tsInfo = resolve(ModelAttributeResolver::class)->resolveAttribute($scope->varModelBindings[$argExpr->var->name], $propName);

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
                && $this->isProxyFetch($argExpr->var, $scope)
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
     * The list an `EnumResource::collection()` payload publishes: the one enum it holds, wrapped in a list unless the
     * payload already is one; null when no single enum resolves.
     *
     * @return ValueExpressionResult|null
     */
    protected function enumCollectionValue(Expr $payload, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $enumResult = $this->resolveEnumFromPropertyArg($payload, $scope)
            ?? $this->resolveEnumFromResolvedPayload($payload, $scope, $engine, allowsList: true);

        if ($enumResult === null) {
            return null;
        }

        // The resolved property may already be a collection type (an AsEnumCollection cast or a
        // list<Enum> accessor both resolve their own '[]' already) — only wrap when it isn't.
        $type = $enumResult['type'];
        $alreadyCollection = str_ends_with(rtrim(str_replace('| null', '', $type)), '[]');

        return [
            ...$enumResult,
            'type' => $alreadyCollection ? $type : ValueResult::arrayWrapType($type),
        ];
    }

    /**
     * The one enum a payload those spellings cannot read resolves to, such as a helper's return or a coalesce: its
     * type, `| null` kept, and its optional flag, on the wrap's `enumFqcn`. A collection's payload may also be a list
     * of that enum.
     *
     * @return ValueExpressionResult|null null without imports, for several enums, or a type holding more than the enum
     */
    protected function resolveEnumFromResolvedPayload(
        Expr $payload,
        AnalysisScope $scope,
        ExpressionEngine $engine,
        bool $allowsList = false,
    ): ?array {
        if (! $scope->carriesImports) {
            return null;
        }

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

        // removeMissingValues() drops a wrap over a missing value too, so a `when*()` payload leaves the key optional.
        return ['type' => $resolved['type'], 'optional' => $resolved['optional'], 'enumFqcn' => $fqcns[0]];
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
            ...(($tsInfo['keepsKeys'] ?? false) ? ['keepsKeys' => true] : []),
        ];
    }
}
