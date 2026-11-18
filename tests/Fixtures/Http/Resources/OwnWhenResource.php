<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource overriding `when()` with one that writes the model first, so its `when()` is no framework
 * helper that only reads.
 *
 * @mixin Post
 */
final class OwnWhenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['detail' => $this->when($this->is_pinned, $this->title)];
    }

    /**
     * Unpins the model, then decides as the framework's `when()` does.
     */
    protected function when($condition, $value, $default = null): mixed
    {
        $this->resource->is_pinned = false;

        return parent::when(...func_get_args());
    }
}
