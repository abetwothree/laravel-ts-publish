<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Support\Traits\Macroable;

/**
 * A subclass that re-uses `Macroable`, so it and its subclasses keep a macro store of their own.
 */
class MacroStoreResource extends MacroStoreAncestorResource
{
    use Macroable;
}
