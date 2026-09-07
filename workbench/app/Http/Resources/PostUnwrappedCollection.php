<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

/** Inherits `$wrap = null` and declares nothing else — the delegated analysis must still see it. */
class PostUnwrappedCollection extends UnwrappedCollection
{
    public $collects = PostResource::class;
}
