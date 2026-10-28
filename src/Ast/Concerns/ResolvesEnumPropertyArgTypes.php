<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\MatchArm;
use PhpParser\Node\Name;

/**
 * Resolve an enum type — or the model-attribute type backing one — from a property-fetch argument, a local, or the one
 * enum a resolved payload holds, for both resource-construction shapes: `EnumResource::make()`/`::collection()` and
 * `new EnumResource(...)`. A scope that carries no import reads neither a local nor a resolved payload: the body
 * fallback would drop its whole shape for the enum either names.
 *
 * A wrap sends the enum itself, so it always takes the enum's own type name, which the `AsEnum` rewrite replaces. That
 * holds where `toTsType()` names no enum for it, an override or a `custom_ts_mappings` entry answering its type first:
 * such an enum's values carry no enum channel, so the enum is read from the payload itself.
 *
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
            // assigned once. Only the local takes VariableHandler's guard against a cyclic binding: a parameter is
            // never bound to a variable.
            if ($argExpr instanceof Variable && is_string($argExpr->name)) {
                $name = $argExpr->name;

                if (isset($scope->closureParamExprBindings[$name]) && $scope->holdsBinding($argExpr)) {
                    return $this->resolveEnumFromPropertyArg($scope->closureParamExprBindings[$name], $scope);
                }

                $local = $scope->literalLocalBinding($name);

                if ($scope->carriesImports && $local !== null && ! $scope->resolvesBinding($name, $local)) {
                    return $scope->resolvingBinding($name, $local, fn (): ?array => $this->resolveEnumFromPropertyArg($local, $scope));
                }
            }

            // `$this->resource->property` is equivalent to `$this->property`, since $this->resource
            // is the underlying model instance.
            if (
                $argExpr instanceof PropertyFetch
                && $this->isProxyFetch($argExpr->var, $scope)
                && $argExpr->name instanceof Identifier
            ) {
                return $this->resolveEnumFromAttribute($argExpr->name->toString(), $scope);
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
                return [
                    ...$result,
                    'type' => ValueResult::enumTypeName($enumClassName),
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

        return $this->resolveEnumFromAttribute($propName, $scope);
    }

    /**
     * The enum an `EnumResource::make()` or `new EnumResource()` payload wraps. Its null arm stays only where
     * JsonResource::filter() reaches the wrap, as a resource wrap's does: a Collection sends a null payload as `[]`.
     *
     * @return ValueExpressionResult|null
     */
    protected function enumWrapValue(Expr $payload, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $result = $this->resolveEnumFromPropertyArg($payload, $scope)
            ?? $this->resolveEnumFromResolvedPayload($payload, $scope, $engine);

        return $result === null || $scope->filtersNestedResources
            ? $result
            : [...$result, 'type' => ValueResult::stripNullArm($result['type'])];
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

        $enum = match (count($fqcns)) {
            0 => $this->retypedPayloadEnum($payload, $scope),
            1 => $fqcns[0],
            default => null,
        };

        if ($enum === null) {
            return null;
        }

        $enumType = LaravelTsPublish::toTsType($enum)['type'];
        $held = ValueResult::stripNullArm($resolved['type']);

        if ($held !== $enumType && (! $allowsList || $held !== $enumType.'[]')) {
            return null;
        }

        $type = $fqcns === [] ? ValueResult::enumTypeName($enum).($held === $enumType ? '' : '[]') : $resolved['type'];

        // removeMissingValues() drops a wrap over a missing value too, so a `when*()` payload leaves the key optional.
        return [
            'type' => ValueResult::hasNullArm($resolved['type']) ? ValueResult::withNullArm($type) : $type,
            'optional' => $resolved['optional'],
            'enumFqcn' => $enum,
        ];
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

    /**
     * Resolve the enum a model attribute holds, as an EnumResource wrap reads it: by its attribute type, or for an enum
     * `toTsType()` names no enum for, by the class the attribute holds and the attribute's null arm.
     *
     * @return ValueExpressionResult|null
     */
    private function resolveEnumFromAttribute(string $attributeName, AnalysisScope $scope): ?array
    {
        $info = $this->resolveModelAttributeTypeInfo($attributeName, $scope);

        if ($info['enumFqcn'] !== null) {
            return [...ValueResult::unknown(), 'type' => $info['type'], 'enumFqcn' => $info['enumFqcn']];
        }

        $enum = $scope->modelClass === null
            ? null
            : resolve(ModelAttributeResolver::class)->resolveAttributeClass($scope->modelClass, $attributeName);

        if ($enum === null || ! enum_exists($enum)) {
            return null;
        }

        $type = ValueResult::enumTypeName($enum);

        return [
            ...ValueResult::unknown(),
            'type' => ValueResult::hasNullArm($info['type']) ? ValueResult::withNullArm($type) : $type,
            'enumFqcn' => $enum,
        ];
    }

    /**
     * The one enum a payload holds that `toTsType()` names no enum for, an override or a `custom_ts_mappings` entry
     * answering its type.
     *
     * @return class-string|null
     */
    private function retypedPayloadEnum(Expr $payload, AnalysisScope $scope): ?string
    {
        $enums = array_values(array_unique($this->payloadCaseEnums($payload, $scope) ?? []));

        return count($enums) === 1 && enum_exists($enums[0]) && LaravelTsPublish::toTsType($enums[0])['enumFqcns'] === []
            ? $enums[0]
            : null;
    }

    /**
     * The class of each case a payload holds: its receiver class, a constant's case or list of cases, each item of a
     * list literal, each arm of a `match` or a local's one write; null where a part holds a value none of these read.
     *
     * @return list<class-string>|null
     */
    private function payloadCaseEnums(Expr $payload, AnalysisScope $scope): ?array
    {
        if ($payload instanceof ConstFetch && $payload->name->toLowerString() === 'null') {
            return [];
        }

        $classes = resolve(ReceiverClassResolver::class)->resolve($payload, $scope)->classes ?? null;

        return $classes ?? match (true) {
            $payload instanceof Variable && is_string($payload->name) => $this->localCaseEnums($payload->name, $scope),
            $payload instanceof ClassConstFetch => new ValueResolver()->caseEnums($payload, $scope),
            $payload instanceof Array_ => $this->partsCaseEnums(
                array_map(fn (ArrayItem $item): ?Expr => $item->byRef ? null : $item->value, $payload->items),
                $scope,
            ),
            $payload instanceof Match_ => $this->partsCaseEnums(
                array_map(fn (MatchArm $arm): Expr => $arm->body, $payload->arms),
                $scope,
            ),
            default => null,
        };
    }

    /**
     * The class of each case a local's one write holds, or null for a local that has none or is mid-resolution.
     *
     * @return list<class-string>|null
     */
    private function localCaseEnums(string $name, AnalysisScope $scope): ?array
    {
        $local = $scope->literalLocalBinding($name);

        return $local === null
            ? null
            : $scope->resolvingBinding($name, $local, fn (): ?array => $this->payloadCaseEnums($local, $scope));
    }

    /**
     * The class of each case every part holds, or null when one part holds a value no case reading names.
     *
     * @param  array<Expr|null>  $parts
     * @return list<class-string>|null
     */
    private function partsCaseEnums(array $parts, AnalysisScope $scope): ?array
    {
        $enums = [];

        foreach ($parts as $part) {
            $held = $part === null ? null : $this->payloadCaseEnums($part, $scope);

            if ($held === null) {
                return null;
            }

            array_push($enums, ...$held);
        }

        return $enums;
    }
}
