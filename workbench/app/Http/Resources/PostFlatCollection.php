<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Resources\Attributes\Collects;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A ResourceCollection with $wrap = null so the collection IS the array,
 * not wrapped in a 'data' key. Uses #[Collects] to identify the singular resource.
 */
#[Collects(PostResource::class)]
class PostFlatCollection extends ResourceCollection
{
    /** @var string|null Disable wrapping so the collection is emitted as PostResource[] */
    public static $wrap = null;
}
