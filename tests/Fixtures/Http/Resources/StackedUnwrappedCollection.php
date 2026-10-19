<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Workbench\App\Http\Resources\HandoverCollection;

/**
 * Stacked on a body-less collection that wraps in `handovers`, and turns the wrap off for itself.
 */
class StackedUnwrappedCollection extends HandoverCollection
{
    public static $wrap = null;
}
