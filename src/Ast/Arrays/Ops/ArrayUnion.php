<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Arrays\Ops;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\BranchJoin;

/**
 * PHP's `+` of two arrays: every key the left holds stays, and the right's lands only where the left lacks it.
 *
 * @internal
 */
final class ArrayUnion
{
    /**
     * The analysis `$left + $right` holds: on the path where the left lacks each key it may lack, the right's value
     * lands, and the two paths join into $into.
     *
     * @template T of MethodAnalysis
     *
     * @param  T  $left
     * @param  T  $right
     * @param  T  $into  an empty analysis the two paths join into
     * @return T
     */
    public static function plusAnalyses(MethodAnalysis $left, MethodAnalysis $right, MethodAnalysis $into): MethodAnalysis
    {
        // With no key the left may lack, both paths are one.
        if (! array_any($left->properties, fn (array $prop): bool => $prop['optional'])) {
            $right->dropKeysHeldBy($left);
            $left->merge($right);

            return $left;
        }

        $present = clone $left;
        $present->properties = array_map(
            fn (array $prop): array => MethodAnalysis::withPresence(
                $prop, optional: false, absent: false, removed: $prop['removed'] ?? false,
            ),
            $left->properties,
        );
        $absent = clone $left;
        $absent->properties = [];

        foreach ($left->properties as $prop) {
            if ($prop['optional']) {
                $absent->forgetChannels($prop['name']);
            } else {
                $absent->properties[] = $prop;
            }
        }

        foreach ([$present, $absent] as $branch) {
            $added = clone $right;
            $added->dropKeysHeldBy($branch);
            $branch->merge($added);
        }

        BranchJoin::joinInto($into, [$present, $absent]);

        return $into;
    }
}
