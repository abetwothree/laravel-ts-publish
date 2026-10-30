<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/**
 * A subclass declaring the method its ancestor's `toArray()` calls, under the name a test registers a macro by.
 */
class MacroShadowResource extends MacroAncestorResource
{
    /** The subclass's own answer, which `static::` reaches and the ancestor's `self::` does not. */
    public function ancestorProbe(): int
    {
        return 1;
    }
}
