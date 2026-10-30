<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/**
 * A subclass declaring the method its ancestor's `toArray()` calls through `self::`, under its store's macro name.
 */
class MacroStoreShadowResource extends MacroStoreResource
{
    /** The subclass's own answer, which the ancestor's `self::` call never reaches. */
    public function storeProbe(): bool
    {
        return true;
    }
}
