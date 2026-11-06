<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use LogicException;

/** Redeclares its parent's stop() and halt() as `never`. */
final class StoppingChild extends StoppingParent
{
    /** Throws. */
    public static function stop(): never
    {
        throw new LogicException('Stopped.');
    }

    /** Throws, though the parent's own halt() never calls it. */
    private function halt(): never
    {
        throw new LogicException('Halted.');
    }
}
