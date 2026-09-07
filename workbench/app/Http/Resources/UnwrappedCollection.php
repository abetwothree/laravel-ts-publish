<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/** Base collection that turns off the `data` envelope for every subclass. */
abstract class UnwrappedCollection extends ResourceCollection
{
    public static $wrap = null;
}
