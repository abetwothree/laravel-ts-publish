<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Overrides `__isset()` to deny every member `__get()` forwards to its post, beside a property it declares, which
 * isset() reads in place.
 *
 * @mixin Post
 */
final class IssetDenyingResource extends JsonResource
{
    public ?bool $pinned = null;

    /**
     * Denies every member the resource does not declare.
     */
    public function __isset($key): bool
    {
        return false;
    }
}
