<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Arrays\Ops;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Print\ShapeLowering;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Read\ArrayShapes;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ArrayShape;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\KeyValue;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeKey;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeObserver;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeSignature;

/**
 * PHP's `+` of two arrays: every key the left holds stays, and the right's lands only where the left lacks it.
 *
 * @internal
 */
final class ArrayUnion
{
    /**
     * The shape `$left + $right` holds, in PHP's key order: the left's entries, then each right entry the left lacks. A
     * key the left may leave unset joins the right's value on that path, as $options joins a key's values; one it holds
     * on every path, a `when()` key's removable value included, keeps its own.
     */
    public static function plus(ArrayShape $left, ArrayShape $right, JoinOptions $options = new JoinOptions): ArrayShape
    {
        $added = self::withExtras($right, null);
        $sums = array_map(static fn (ArrayShape $path): ArrayShape => ShapeWrites::merged(
            $path,
            self::besideExtras(ShapeWrites::withoutKeys($added, self::names($path)), $left->extras),
            false,
        ), self::paths(self::withExtras($left, null)));
        $sum = count($sums) === 1 ? $sums[0] : ShapeJoin::exclusive($sums, $options);
        $extras = array_values(array_filter([$left->extras, $right->extras]));

        return $extras === [] ? $sum : self::withExtras($sum, ShapeJoin::value($extras, new JoinOptions));
    }

    /**
     * The analysis `$left + $right` holds, as plus() reads the two lifted, written into $into, replacing what it held.
     * It reads both operands before it writes $into, so either may be $into.
     *
     * @template T of MethodAnalysis
     *
     * @param  T  $left
     * @param  T  $right
     * @param  T  $into
     * @return T
     */
    public static function plusAnalyses(
        MethodAnalysis $left,
        MethodAnalysis $right,
        MethodAnalysis $into,
        JoinOptions $options = new JoinOptions,
    ): MethodAnalysis {
        $union = self::plus(ArrayShapes::ofAnalysis($left), ArrayShapes::ofAnalysis($right), $options);
        ShapeLowering::into($into, $union);
        resolve(ShapeObserver::class)->observed($into);

        return $into;
    }

    /**
     * Each named key some path may leave the array without, which a `+` lays the right's value on, in order.
     *
     * @return list<string>
     */
    public static function unsetKeys(ArrayShape $shape): array
    {
        $unset = [];

        foreach ($shape->entries as $entry) {
            if ($entry instanceof ShapeKey && $entry->presence->mayBeUnset()) {
                $unset[$entry->name] = $entry->name;
            }
        }

        return array_values($unset);
    }

    /**
     * The keys the right adds with the left's extras joined into each value, since the left may hold any of them.
     */
    private static function besideExtras(ArrayShape $added, ?KeyValue $extras): ArrayShape
    {
        if ($extras === null) {
            return $added;
        }

        $joined = static fn (ShapeKey|ShapeSignature $entry): ShapeKey|ShapeSignature => $entry instanceof ShapeKey
            ? ShapeWrites::withValue(
                $entry, ShapeJoin::value([$entry->value, $extras], new JoinOptions), $entry->presence,
            )
            : $entry;

        return ShapeWrites::mapped($added, null, $joined);
    }

    /**
     * The left on each path the right lands on: where it holds each key it may leave unset, then where it lacks them
     * all; one path where it holds every key on every path.
     *
     * @return non-empty-list<ArrayShape>
     */
    private static function paths(ArrayShape $left): array
    {
        $unset = self::unsetKeys($left);

        if ($unset === []) {
            return [$left];
        }

        $held = static fn (ShapeKey|ShapeSignature $entry): ShapeKey|ShapeSignature => $entry instanceof ShapeKey
            && in_array($entry->name, $unset, true)
            ? ShapeWrites::withValue($entry, $entry->value, $entry->presence->kept())
            : $entry;

        return [ShapeWrites::mapped($left, null, $held), array_reduce($unset, ShapeWrites::without(...), $left)];
    }

    /**
     * The name each entry of a shape is written under.
     *
     * @return list<string>
     */
    private static function names(ArrayShape $shape): array
    {
        return array_map(static fn (ShapeKey|ShapeSignature $entry): string => $entry->writtenName(), $shape->entries);
    }

    /**
     * A shape holding other extras, all else kept.
     */
    private static function withExtras(ArrayShape $shape, ?KeyValue $extras): ArrayShape
    {
        return new ArrayShape($shape->entries, $extras, $shape->opaque, $shape->nonEmptyProof, $shape->wide);
    }
}
