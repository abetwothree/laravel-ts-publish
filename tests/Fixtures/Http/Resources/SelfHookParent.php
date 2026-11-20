<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A resource whose `tick` get hook runs this class's clearTick() through `self::`, which clears the pin.
 *
 * @mixin Post
 */
class SelfHookParent extends JsonResource
{
    public bool $tick = true {
        get {
            self::clearTick();

            return $this->tick;
        }
    }

    /**
     * Clears the pin.
     */
    public function clearTick(): void
    {
        $this->resource->is_pinned = false;
    }
}
