<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/** Untyped methods that end in a call to a stop its child redeclares as `never`, through self, static or a private. */
class StoppingParent
{
    /** Returns. */
    public static function stop(): void {}

    /** Ends in self::stop(), which runs this class's stop(). */
    public function endsInSelfStop($value)
    {
        if (is_string($value)) {
            return 'x';
        }

        self::stop();
    }

    /** Ends in static::stop(), which runs the child's. */
    public function endsInStaticStop($value)
    {
        if (is_string($value)) {
            return 'x';
        }

        static::stop();
    }

    /** Ends in its own private halt(), which no child overrides. */
    public function endsInPrivateHalt($value)
    {
        if (is_string($value)) {
            return 'x';
        }

        $this->halt();
    }

    /** Returns. */
    private function halt(): void {}
}
