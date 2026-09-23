<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;

/**
 * Weighs what an inline `@var` declares against what the engine reads without it, one rule for values and receivers:
 * a known reading stands where the declaration admits or contradicts it, and the declaration fills an unknown or
 * vaguer reading. A `@var` is a hint, so only `#[TsCasts]` overrides a known reading.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class DeclaredTypeWeigher
{
    /** The scalar kinds TsTypeShape::scalarKind() names; any other kind is a class. */
    private const array SCALARS = ['string', 'number', 'boolean', 'null'];

    /**
     * The value a declared local publishes. An unsure comparison, such as a `Pick<…>`, an intersection or an index
     * signature against the declaration, keeps the declaration.
     *
     * @param  ValueExpressionResult  $declared
     * @param  ValueExpressionResult|null  $reading
     * @return ValueExpressionResult
     */
    public function value(array $declared, ?array $reading): array
    {
        if ($reading === null || TsTypeString::isVagueTsType($reading['type'])) {
            return $declared;
        }

        return TsTypeShape::admits($declared['type'], $reading['type'])
            || $this->excludes($this->armKinds($declared), $this->armKinds($reading))
            ? $reading
            : $declared;
    }

    /**
     * The receiver a declared local holds. A declaration naming a subclass of what the engine reads narrows it, and
     * one involving an interface is unsure, so both keep the declaration.
     *
     * @param  non-empty-list<class-string>  $declared
     */
    public function receiver(array $declared, ?ReceiverType $reading): ReceiverType
    {
        return $reading !== null && ($reading->within($declared) || $this->excludes($declared, $reading->classes))
            ? $reading
            : new ReceiverType($declared);
    }

    /**
     * Whether no kind of one side can be a kind of the other; false when either side is unsure.
     *
     * @param  list<string>|null  $kinds
     * @param  list<string>|null  $others
     */
    private function excludes(?array $kinds, ?array $others): bool
    {
        return $kinds !== null && $others !== null && $kinds !== [] && $others !== [] && array_all(
            $kinds,
            fn (string $kind): bool => array_all($others, fn (string $other): bool => $this->disjoint($kind, $other)),
        );
    }

    /**
     * Whether two kinds share no value: two different scalar kinds, a scalar and a class, or two classes, neither an
     * interface, where neither extends the other.
     */
    private function disjoint(string $kind, string $other): bool
    {
        if (in_array($kind, self::SCALARS, true) || in_array($other, self::SCALARS, true)) {
            return $kind !== $other;
        }

        return $this->isClass($kind)
            && $this->isClass($other)
            && ! is_a($kind, $other, true)
            && ! is_a($other, $kind, true);
    }

    /**
     * Each union arm of a value's type as its scalar kind, or as the model class its channel names; null when any arm
     * is neither, so the comparison is unsure.
     *
     * @param  ValueExpressionResult  $result
     * @return list<string>|null
     */
    private function armKinds(array $result): ?array
    {
        $model = $result['modelFqcn'] ?? null;
        $kinds = [];

        foreach (TsTypeString::splitTopLevelUnion($result['type']) as $arm) {
            $kind = TsTypeShape::scalarKind($arm) ?? ($model !== null && $arm === class_basename($model) ? $model : null);

            if ($kind === null) {
                return null;
            }

            $kinds[] = $kind;
        }

        return $kinds;
    }

    /**
     * Whether a name is a loadable class or enum, not an interface, which a class unrelated to it may still implement.
     */
    private function isClass(string $name): bool
    {
        return class_exists($name) && ! interface_exists($name);
    }
}
