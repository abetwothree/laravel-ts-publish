<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource merging an array whose literal spread overrides the key before it.
 *
 * @mixin Post
 */
final class SpreadOverrideMergeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            $this->merge(['by' => $this->author, ...['by' => $this->title]]),
        ];
    }
}
