<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Analysis;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;
use Closure;
use PhpParser\Node\Expr;

/**
 * The arms only a declaration produced, marked (VisibleGaps) while a read runs under an outer declaration, an inline
 * `@var`, a `toArray()` `@return` key or a closure `@return`, so that declaration replaces them and keeps every arm the
 * body proves: a nullable column's, cast's or relation's `null`, a `?T` return's or parameter's, a lookup's that may
 * find nothing (`find()`, `first()`, `value()`, `auth()->user()`, `tryFrom()`), the `null` of `?->` over a receiver
 * that holds no proven one, and the `Record` a map over an untyped array hedges its keys with.
 *
 * Out of the declaration mode (AnalysisMemo::markingDeclared()) every source reads as it always has.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class DeclaredArms
{
    /**
     * A type wholly a declaration's, a signature's, a column row's or a relation's, with its top-level `null` marked as
     * only that declaration's, in the declaration mode.
     */
    public static function nullable(string $type): string
    {
        return self::marking() ? VisibleGaps::nullMarked($type) : $type;
    }

    /**
     * A value with a `null` beside it that only a declaration adds, as a lookup that may find nothing does: marked in
     * the declaration mode, every `null` the value itself holds kept as it is.
     */
    public static function withDeclaredNull(string $type): string
    {
        return self::marking() && $type !== 'unknown'
            ? VisibleGaps::restored($type, [VisibleGaps::DECLARED_NULL])
            : TsTypeString::withNullArm($type);
    }

    /**
     * A model attribute's type with its `null` marked as only a declaration's, in the declaration mode, where a
     * nullable column declares it, cast or not; an accessor's `null` may be one its body returns, so it stays.
     *
     * @param  class-string  $modelFqcn
     */
    public static function attribute(string $type, string $modelFqcn, string $name): string
    {
        if (! self::marking()) {
            return $type;
        }

        $attribute = resolve(ModelAttributeResolver::class)->getAttributes($modelFqcn)?->firstWhere('name', $name);

        return $attribute !== null && $attribute['nullable'] && ! in_array($attribute['cast'], ['attribute', 'accessor'], true)
            ? VisibleGaps::nullMarked($type)
            : $type;
    }

    /**
     * A member read through `?->` with the `null` its short circuit adds: marked as only a declaration's in the
     * declaration mode, unless the receiver's own reading holds a `null` the body proves, as a ternary's does.
     */
    public static function nullsafe(string $type, Expr $receiver, ExpressionEngine $engine): string
    {
        if (! self::marking() || $type === 'unknown') {
            return TsTypeString::withNullArm($type);
        }

        $held = DroppedUnionArms::provisional(static fn (): array => $engine->resolve($receiver), static fn (): bool => false);

        return in_array('null', TsTypeString::splitTopLevelUnion($held['type']), true)
            ? TsTypeString::withNullArm($type)
            : self::withDeclaredNull($type);
    }

    /**
     * What a map makes of an array no rule typed, a list beside a `Record`, with the `Record` marked as only PHP's
     * `array` declaring its keys, in the declaration mode.
     */
    public static function keysHedged(string $mapped): string
    {
        return self::marking() ? VisibleGaps::restored($mapped, [VisibleGaps::DECLARED_KEYS]) : $mapped;
    }

    /**
     * A value an outer declaration types, read in the declaration mode and filled by it.
     *
     * The outermost fill turns each mark it left back into what it stands for. A fill nested in another's read keeps
     * them, and its own declaration's `null` is marked too, since only a declaration says it.
     *
     * @template TRead
     *
     * @param  Closure(): TRead  $read
     * @param  Closure(TRead, bool): (ValueExpressionResult|null)  $fill  given the read and whether the fill is nested
     * @return ValueExpressionResult|null
     */
    public static function underDeclaration(Closure $read, Closure $fill): ?array
    {
        $memo = resolve(AnalysisMemo::class);
        $nested = $memo->marksDeclared();
        $filled = $fill($memo->markingDeclared($read), $nested);

        return $nested || $filled === null ? $filled : self::unmarked($filled);
    }

    /**
     * A declaration laid over a value as a fill nested in another's read lays it: its own `null` marked.
     *
     * @param  ValueExpressionResult  $declared
     * @return ValueExpressionResult
     */
    public static function asNested(array $declared): array
    {
        return [...$declared, 'type' => VisibleGaps::nullMarked($declared['type'])];
    }

    /**
     * A result with each mark turned back into what it stands for, in its type and in the one object a split literal
     * prints.
     *
     * @param  ValueExpressionResult  $result
     * @return ValueExpressionResult
     */
    public static function unmarked(array $result): array
    {
        $unsplit = $result['unsplit'] ?? null;

        // Variants print unmarked, so a split literal's union may hold no mark where its one-object print still does.
        if (! VisibleGaps::holdsMarks($result['type']) && ($unsplit === null || ! VisibleGaps::holdsMarks($unsplit['print']['type']))) {
            return $result;
        }

        return [
            ...$result,
            'type' => VisibleGaps::unmarked($result['type']),
            ...($unsplit === null ? [] : ['unsplit' => [
                'of' => VisibleGaps::unmarked($unsplit['of']),
                'print' => [...$unsplit['print'], 'type' => VisibleGaps::unmarked($unsplit['print']['type'])],
            ]]),
        ];
    }

    /**
     * Whether the read under way marks the arms only a declaration produced.
     */
    private static function marking(): bool
    {
        return resolve(AnalysisMemo::class)->marksDeclared();
    }
}
