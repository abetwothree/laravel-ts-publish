<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Variants;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Values\InlineObjectResult;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Support\VariantRows;

/**
 * A top-level analysis with each group of keys that follow a discriminant published as one row: the union of one
 * object per variant, holding the discriminant's literal and each dependent's arm in item order.
 *
 * @internal
 */
final class VariantUnions
{
    /**
     * Replace the rows of each group that still holds for the analysis, none of whose keys a cast the publisher lays
     * over it retypes, and whose objects queue their enums apart, with one variant row after every other row.
     *
     * @param  list<string>  $declinedKeys  the keys a later cast retypes, whose arms would describe rows not published
     */
    public static function apply(MethodAnalysis $analysis, array $declinedKeys): void
    {
        $groups = array_filter(
            $analysis->variants->groups ?? [],
            static fn (VariantGroup $group): bool => $group->holdsFor($analysis)
                && array_intersect($group->keys, $declinedKeys) === [],
        );

        foreach ($groups as $group) {
            $objects = array_map(static fn (Variant $variant): MethodAnalysis => self::object($group, $variant), $group->variants);

            if (! array_all($objects, InlineObjectResult::queuesEnumsApart(...))) {
                continue;
            }

            $union = ValueResult::unionResults(array_map(resolve(InlineObjectResult::class)->of(...), $objects));

            $analysis->dropKeys($group->keys);
            $analysis->addProperty(VariantRows::key($group->discriminant), $union);
        }
    }

    /**
     * One variant's object: the discriminant's literal and each dependent's arm, in item order, a removed one never.
     */
    private static function object(VariantGroup $group, Variant $variant): MethodAnalysis
    {
        $object = new MethodAnalysis;

        $sent = $group->sent($variant);

        // The names come from the group's keys: PHP turns a numeric name into an int where it keys `$sent`.
        foreach ($group->keys as $key) {
            $object->addProperty($key, $sent[$key] ?? ValueResult::removed());
        }

        return $object;
    }
}
