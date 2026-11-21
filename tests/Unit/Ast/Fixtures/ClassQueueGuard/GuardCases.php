<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ClassQueueGuard;

use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * The guard's seeded generator: the bodies of one family, each a shape over two to four arms of the family's two
 * kinds, both of them written at least once, drawn without replacement. An untyped arm is written only where branches
 * join.
 */
final class GuardCases
{
    /**
     * $count of the family's bodies, $first and $second, shuffled by $seed and the family alone, so a family draws the
     * same bodies whichever rows run beside it; every body once $count reaches how many the family has.
     *
     * @return list<GuardCase>
     */
    public static function of(ArmKind $first, ArmKind $second, int $seed, int $count): array
    {
        $family = hash('sha256', "{$seed}|{$first->value}|{$second->value}", true);
        $bodies = [];

        $untyped = $first === ArmKind::Untyped || $second === ArmKind::Untyped;

        foreach (array_filter(BodyShape::cases(), static fn (BodyShape $shape): bool => ! $untyped || $shape->joinsBranches()) as $shape) {
            foreach (self::armLists($first, $second) as $arms) {
                $bodies[] = new GuardCase($shape, $arms);
            }
        }

        return array_slice(new Randomizer(new Xoshiro256StarStar($family))->shuffleArray($bodies), 0, $count);
    }

    /**
     * Every written order of two to four arms of the two kinds that writes both.
     *
     * @return list<non-empty-list<ArmKind>>
     */
    private static function armLists(ArmKind $first, ArmKind $second): array
    {
        $lists = [];

        for ($length = 2; $length <= 4; $length++) {
            for ($bits = 1; $bits < (1 << $length) - 1; $bits++) {
                $lists[] = array_map(
                    static fn (int $at): ArmKind => (($bits >> $at) & 1) === 1 ? $second : $first,
                    range(0, $length - 1),
                );
            }
        }

        return $lists;
    }
}
