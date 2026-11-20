<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ClassQueueGuard;

use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * The guard's seeded generator: the bodies of one family, each a shape over two to four arms of the family's two
 * kinds, both of them written at least once.
 */
final class GuardCases
{
    /**
     * $count bodies of the family $first and $second, drawn from $seed and the family alone, so a family draws the same
     * bodies whichever rows run beside it.
     *
     * @return list<GuardCase>
     */
    public static function of(ArmKind $first, ArmKind $second, int $seed, int $count): array
    {
        $family = hash('sha256', "{$seed}|{$first->value}|{$second->value}", true);
        $random = new Randomizer(new Xoshiro256StarStar($family));
        $shapes = BodyShape::cases();
        $cases = [];

        for ($drawn = 0; $drawn < $count; $drawn++) {
            $arms = [$first, $second];

            for ($extra = $random->getInt(0, 2); $extra > 0; $extra--) {
                $arms[] = $random->getInt(0, 1) === 0 ? $first : $second;
            }

            $cases[] = new GuardCase($shapes[$random->getInt(0, count($shapes) - 1)], $random->shuffleArray($arms));
        }

        return $cases;
    }
}
