<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns\Resources;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\DeclaredClasses;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use ReflectionMethod;

/**
 * Resolve an enum type — or the model-attribute type backing one — from a property-fetch argument, a local, or the one
 * enum a resolved payload holds, for both resource-construction shapes: `EnumResource::make()`/`::collection()` and
 * `new EnumResource(...)`. A scope that carries no import reads neither a local nor a resolved payload: the body
 * fallback would drop its whole shape for the enum either names.
 *
 * A wrap sends the enum itself, so it takes the enum's own type name, which the `AsEnum` rewrite replaces, even where
 * `toTsType()` names no enum for it (an override or a `custom_ts_mappings` entry): its case still carries the enum
 * channel, while a cast, an accessor or a declared type is read from its class.
 *
 * Requires the host to also `use Ast\Concerns\InspectsAstNodes` (for `isThisPropertyFetch()` and `soleSpelledWrite()`).
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type TypesImportMap from Datable
 *
 * @phpstan-type AttributeTypeInfo = array{
 *      type: string,
 *      enumFqcn: class-string|null,
 *      enumFqcns: list<class-string>,
 *      classFqcns: list<class-string>,
 *      classTokenFqcns?: list<class-string|null>,
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
            // A bare $variable may be a closure parameter bound to $this->prop by a when() condition, or a local whose
            // read sees one write; a join reads as its resolved value. Only the local takes VariableHandler's guard
            // against a cyclic binding: a parameter is never bound to a variable.
            if ($argExpr instanceof Variable && is_string($argExpr->name)) {
                $name = $argExpr->name;

                if (isset($scope->closureParamExprBindings[$name]) && $scope->holdsBinding($argExpr)) {
                    return $this->resolveEnumFromPropertyArg($scope->closureParamExprBindings[$name], $scope);
                }

                $local = $this->soleSpelledWrite($argExpr, $scope);

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

            // `Enum::Case`, or a static call such as `Enum::from(...)` that returns the enum.
            $enum = $argExpr instanceof StaticCall || $argExpr instanceof ClassConstFetch
                ? $this->heldEnum($argExpr, $scope)
                : null;

            if ($enum === null) {
                return null;
            }

            $type = ValueResult::enumTypeName($enum);

            return [
                ...$result,
                'type' => $argExpr instanceof StaticCall && $this->returnsNull($argExpr, $scope) ? TsTypeString::withNullArm($type) : $type,
                'enumFqcn' => $enum,
            ];
        }

        /** @var PropertyFetch $argExpr */
        $propName = $argExpr->name instanceof Identifier ? $argExpr->name->toString() : null;

        if ($propName === null) {
            return null; // @codeCoverageIgnore
        }

        return $this->resolveEnumFromAttribute($propName, $scope);
    }

    /**
     * The enum an `EnumResource::make()` or `new EnumResource()` payload wraps.
     *
     * A null payload no filter() reaches, in a page, an event or a map() kept as a Collection, is sent as the `[]`
     * resolve() returns, spelled `never[]`.
     *
     * @return ValueExpressionResult|null
     */
    protected function enumWrapValue(Expr $payload, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $result = $this->resolveEnumFromPropertyArg($payload, $scope)
            ?? $this->resolveEnumFromResolvedPayload($payload, $scope, $engine);

        if ($result === null || ! TsTypeString::hasNullArm($result['type']) || $engine->reading()->filterReaches($scope)) {
            return $result;
        }

        return [...$result, 'type' => TsTypeString::withoutNullArm($result['type']).' | never[]'];
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
        $alreadyCollection = ValueResult::isListShaped($type);

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

        $resolved = $scope->readingCasesAsEnums(fn (): array => $engine->resolve($payload));
        // A union of enum reads, such as a `??` over a case, carries its one enum on the multi-entry channel.
        $fqcns = array_values(array_unique([
            ...(isset($resolved['directEnumFqcn']) ? [$resolved['directEnumFqcn']] : []),
            ...($resolved['embeddedEnumFqcns'] ?? []),
        ]));

        $class = $fqcns === [] ? $this->heldEnum($payload, $scope) : null;
        $enum = match (count($fqcns)) {
            // A value of a type an override or a `custom_ts_mappings` entry answers carries no enum channel.
            0 => $class !== null && LaravelTsPublish::retypedEnumType($class) !== null ? $class : null,
            1 => $fqcns[0],
            default => null,
        };

        if ($enum === null) {
            return null;
        }

        $retyped = LaravelTsPublish::retypedEnumType($enum);
        $enumType = $retyped ?? ValueResult::enumTypeName($enum);
        $held = TsTypeString::withoutNullArm($resolved['type']);

        if ($held !== $enumType && (! $allowsList || $held !== $enumType.'[]')) {
            return null;
        }

        $type = $retyped === null
            ? $resolved['type']
            : ValueResult::enumTypeName($enum).($held === $enumType ? '' : '[]');

        // removeMissingValues() drops a wrap over a missing value too, so a `when*()` payload leaves the key optional.
        return [
            'type' => TsTypeString::hasNullArm($resolved['type']) ? TsTypeString::withNullArm($type) : $type,
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
     * @param  bool  $casesAsLiterals  the scope's own flag where the attribute is read as a value; an `EnumResource`
     *                                 payload or a `->value` base needs the enum, so it reads cases as their enum
     * @return AttributeTypeInfo
     */
    protected function resolveModelAttributeTypeInfo(
        string $attributeName,
        AnalysisScope $scope,
        bool $casesAsLiterals = false,
    ): array {
        if ($scope->modelClass === null) {
            return ['type' => 'unknown', 'enumFqcn' => null, 'enumFqcns' => [], 'classFqcns' => [], 'customImports' => []];
        }

        $tsInfo = resolve(ModelAttributeResolver::class)
            ->resolveAttribute($scope->modelClass, $attributeName, $scope->carriesImports, $casesAsLiterals);

        /** @var class-string|null $enumFqcn */
        $enumFqcn = $tsInfo['enumFqcns'][0] ?? null;

        return [
            'type' => $tsInfo['type'],
            'enumFqcn' => $enumFqcn,
            'enumFqcns' => $tsInfo['enumFqcns'],
            'classFqcns' => $tsInfo['classFqcns'],
            // Present only where the type spells one name for two classes or an override spells a class's name; see
            // TypeScriptTypeInfo.
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
            'type' => TsTypeString::hasNullArm($info['type']) ? TsTypeString::withNullArm($type) : $type,
            'enumFqcn' => $enum,
        ];
    }

    /**
     * The one enum class an expression holds, as the receiver resolver names it; null for any other class, or several.
     *
     * @return class-string|null
     */
    private function heldEnum(Expr $expr, AnalysisScope $scope): ?string
    {
        $classes = resolve(ReceiverClassResolver::class)->resolve($expr, $scope)->classes ?? [];

        return count($classes) === 1 && enum_exists($classes[0]) ? $classes[0] : null;
    }

    /**
     * Whether a static call's declared return type admits null, as `tryFrom()`'s does for a value no case backs.
     */
    private function returnsNull(StaticCall $call, AnalysisScope $scope): bool
    {
        if (! $call->name instanceof Identifier) {
            return false;
        }

        $method = $call->name->toString();
        $classes = resolve(ReceiverClassResolver::class)->resolveStaticReceiver($call, $scope)->classes ?? [];

        return array_any($classes, static fn (string $class): bool => method_exists($class, $method)
            && (DeclaredClasses::reflectedReturnType(new ReflectionMethod($class, $method))?->allowsNull() ?? false));
    }
}
