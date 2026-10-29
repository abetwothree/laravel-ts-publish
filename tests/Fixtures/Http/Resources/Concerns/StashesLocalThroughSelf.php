<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Concerns;

/** Passes a local to `self::fill()`, which names the class that uses this trait, not the trait. */
trait StashesLocalThroughSelf
{
    /** @return array<string, mixed> */
    protected function traitStashed(): array
    {
        $out = ['seed' => 1];
        self::fill($out);

        return ['trait_stash' => $out];
    }
}
