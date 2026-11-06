<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Workbench\App\Models\Post;

/**
 * A resource with no model whose mixin names an interface a post implements, and an `if` that proves a post.
 *
 * @mixin JsonSerializable
 */
final class GuardedJsonSerializableResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof Post) {
            return ['t' => $this->title, 'k' => $this->getKey()];
        }

        return ['t' => null, 'k' => null];
    }
}
