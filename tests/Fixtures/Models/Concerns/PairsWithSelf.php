<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\Concerns;

/** A helper whose parameters are typed `self` and `parent`, naming the model that uses this trait and its parent. */
trait PairsWithSelf
{
    /** Match this model with another of its class, a spare one or none, and its base. */
    public function matchedWith(self $other, ?self $spare, parent $base): void {}
}
