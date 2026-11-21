<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Enums;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\Resources\ResolvesEnumPropertyArgTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\DeclaredClasses;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Values\TokenKind;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use ReflectionMethod;

/**
 * The enum an `EnumResource` payload holds, as `EnumResource::make()`/`::collection()` and `new EnumResource(...)` wrap
 * it: from a property-fetch argument, a local, or the one enum a resolved payload holds. A scope that carries no import
 * reads neither a local nor a resolved payload: the body fallback would drop its whole shape for the enum either names.
 *
 * A wrap sends the enum itself, so it takes the enum's own type name, which spelledWrap() spells as the wrap, even
 * where `toTsType()` names no enum for it (an override or a `custom_ts_mappings` entry): its case still carries the
 * enum channel, while a cast, an accessor or a declared type is read from its class.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class EnumWrapPayload
{
    use InspectsAstNodes;
    use ResolvesEnumPropertyArgTypes;

    /**
     * What an `EnumResource::make()` or `new EnumResource()` payload publishes as its wrap.
     *
     * A null payload no filter() reaches, in a page, an event or a map() kept as a Collection, is sent as the `[]`
     * resolve() returns, spelled `never[]`.
     *
     * @return ValueExpressionResult|null
     */
    public function value(Expr $payload, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $result = $this->fromArgument($payload, $scope)
            ?? $this->fromResolvedPayload($payload, $scope, $engine);

        if ($result === null) {
            return null;
        }

        if (! TsTypeString::hasNullArm($result['type']) || $engine->reading()->filterReaches($scope)) {
            return $this->spelledWrap($result);
        }

        return $this->spelledWrap([...$result, 'type' => TsTypeString::withoutNullArm($result['type']).' | never[]']);
    }

    /**
     * The list an `EnumResource::collection()` payload publishes: the one enum it holds, wrapped in a list unless the
     * payload already is one; null when no single enum resolves.
     *
     * The collection resolves each element itself, which no filter() reaches, so a null element is the `[]` resolve()
     * returns for it.
     *
     * @return ValueExpressionResult|null
     */
    public function collectionValue(Expr $payload, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $enumResult = $this->fromArgument($payload, $scope)
            ?? $this->fromResolvedPayload($payload, $scope, $engine, allowsList: true);

        if ($enumResult === null) {
            return null;
        }

        // The resolved property may already be a collection type (an AsEnumCollection cast or a
        // list<Enum> accessor both resolve their own '[]' already) — only wrap when it isn't.
        $type = $enumResult['type'];
        $alreadyCollection = ValueResult::isListShaped($type);

        return $this->spelledWrap([
            ...$enumResult,
            'type' => $alreadyCollection ? self::nullElementsResolved($type) : ValueResult::arrayWrapType($type),
        ]);
    }

    /**
     * A payload's enum as its wrap publishes it: `AsEnum<typeof C>` with the tolki package on, else its value type.
     *
     * Each token rides the queue its spelling is read by, and the enum `wrappedEnumFqcns`; no read's channel holds it,
     * so no reader takes the wrap for a direct read of its enum.
     *
     * @param  ValueExpressionResult  $payload  a payload reader's result, the enum on `enumFqcn`
     * @return ValueExpressionResult
     */
    public function spelledWrap(array $payload): array
    {
        $enum = $payload['enumFqcn'] ?? null;
        unset($payload['enumFqcn']);

        if ($enum === null) {
            return $payload; // @codeCoverageIgnore
        }

        $spelled = EnumWrapSpelling::enabled();
        $kind = $spelled ? TokenKind::WrapConst : TokenKind::Enum;
        $type = $spelled
            ? TsTypeString::substituteEnumType(
                $payload['type'],
                EnumNames::typeName($enum),
                EnumWrapSpelling::ofEnum($enum),
            )
            : $payload['type'];
        $pattern = TsTypeString::queuedTokenPattern([$kind->nameOf($enum)], $kind->anchor());
        $tokens = (int) preg_match_all($pattern, $type);
        $channel = $spelled ? 'embeddedEnumResourceFqcns' : 'embeddedEnumFqcns';

        return [
            ...$payload,
            'type' => $type,
            ...($tokens > 0 ? [$channel => array_fill(0, $tokens, $enum)] : []),
            'wrappedEnumFqcns' => [$enum],
        ];
    }

    /**
     * A list of an enum with a null its element may hold spelled as the `[]` the element's own resolve() returns.
     */
    private static function nullElementsResolved(string $list): string
    {
        $element = TsTypeShape::elementType(TsTypeString::withoutNullArm($list));

        if ($element === null || ! TsTypeString::hasNullArm($element)) {
            return $list;
        }

        $resolved = ValueResult::arrayWrapType(TsTypeString::withoutNullArm($element).' | never[]');

        return TsTypeString::withNullArmOf($list, $resolved);
    }

    /**
     * The enum a payload argument spells: a model attribute read through `$this`, a bound or local variable, a case or
     * a static call returning the enum.
     *
     * @return ValueExpressionResult|null
     */
    private function fromArgument(Expr $argExpr, AnalysisScope $scope): ?array
    {
        $result = ValueResult::unknown();

        if (! $this->isThisPropertyFetch($argExpr)) {
            // A bare $variable may be a closure parameter bound to $this->prop by a when() condition, or a local whose
            // read sees one write; a join reads as its resolved value. Only the local takes VariableHandler's guard
            // against a cyclic binding: a parameter is never bound to a variable.
            if ($argExpr instanceof Variable && is_string($argExpr->name)) {
                $name = $argExpr->name;

                if (isset($scope->closureParamExprBindings[$name]) && $scope->holdsBinding($argExpr)) {
                    return $this->fromArgument($scope->closureParamExprBindings[$name], $scope);
                }

                $local = $this->soleSpelledWrite($argExpr, $scope);

                if ($scope->carriesImports && $local !== null && ! $scope->resolvesBinding($name, $local)) {
                    return $scope->resolvingBinding($name, $local, fn (): ?array => $this->fromArgument($local, $scope));
                }
            }

            // `$this->resource->property` is equivalent to `$this->property`, since $this->resource
            // is the underlying model instance.
            if (
                $argExpr instanceof PropertyFetch
                && $this->isProxyFetch($argExpr->var, $scope)
                && $argExpr->name instanceof Identifier
            ) {
                return $this->fromAttribute($argExpr->name->toString(), $scope);
            }

            // `Enum::Case`, or a static call such as `Enum::from(...)` that returns the enum.
            $enum = $argExpr instanceof StaticCall || $argExpr instanceof ClassConstFetch
                ? $this->heldEnum($argExpr, $scope)
                : null;

            if ($enum === null) {
                return null;
            }

            $type = EnumNames::typeName($enum);

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

        return $this->fromAttribute($propName, $scope);
    }

    /**
     * The one enum a payload those spellings cannot read resolves to, such as a helper's return or a coalesce: its
     * type, `| null` kept, and its optional flag, on the wrap's `enumFqcn`. A collection's payload may also be a list
     * of that enum.
     *
     * @return ValueExpressionResult|null null without imports, for several enums, or a type holding more than the enum
     */
    private function fromResolvedPayload(
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
        $enumType = $retyped ?? EnumNames::typeName($enum);
        $held = TsTypeString::withoutNullArm($resolved['type']);
        // A map over a nullable attribute holds the enum or null in each element; a list with another arm beside it is
        // not one list of the enum.
        $element = $allowsList && count(TsTypeString::splitTopLevelUnion($held)) === 1
            ? TsTypeShape::elementType($held)
            : null;

        if ($held !== $enumType && ! in_array($element, [$enumType, TsTypeString::withNullArm($enumType)], true)) {
            return null;
        }

        $name = EnumNames::typeName($enum);
        $type = match (true) {
            $retyped === null => $resolved['type'],
            $held === $enumType => $name,
            default => ValueResult::arrayWrapType(TsTypeString::withNullArmOf((string) $element, $name)),
        };

        // removeMissingValues() drops a wrap over a missing value too, so a `when*()` payload leaves the key optional.
        return [
            'type' => TsTypeString::withNullArmOf($resolved['type'], $type),
            'optional' => $resolved['optional'],
            'enumFqcn' => $enum,
        ];
    }

    /**
     * Resolve the enum a model attribute holds, as an EnumResource wrap reads it: by its attribute type, or for an enum
     * `toTsType()` names no enum for, by the class the attribute holds and the attribute's null arm.
     *
     * @return ValueExpressionResult|null
     */
    private function fromAttribute(string $attributeName, AnalysisScope $scope): ?array
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

        $type = EnumNames::typeName($enum);

        return [
            ...ValueResult::unknown(),
            'type' => TsTypeString::withNullArmOf($info['type'], $type),
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
