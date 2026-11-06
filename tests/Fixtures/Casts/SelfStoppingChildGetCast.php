<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts;

use LogicException;

/** Redeclares stop() as `never`, which its parent's get() never calls, as `self::` names the parent's own. */
final class SelfStoppingChildGetCast extends SelfStoppingGetCast
{
    /** Throws. */
    public static function stop(): never
    {
        throw new LogicException('Stopped.');
    }
}
