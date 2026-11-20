<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/**
 * A child redeclaring the parent's hooked property with a set hook only, and a clearTick() that reads.
 */
class SelfHookChild extends SelfHookParent
{
    public bool $tick = true {
        set => $value;
    }

    /**
     * Reads the pin, where the parent's clearTick() clears it.
     */
    public function clearTick(): void
    {
        $this->resource->is_pinned;
    }
}
