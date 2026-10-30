<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/**
 * A subclass declaring the method its ancestor's `toArray()` calls, under the name a test registers a macro by.
 */
class MacroShadowResource extends MacroAncestorResource
{
    public function ancestorProbe(): int
    {
        return 1;
    }
}
