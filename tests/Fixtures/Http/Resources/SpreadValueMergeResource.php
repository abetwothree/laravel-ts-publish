<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource merging an array whose spread of a local overrides the key before it.
 *
 * @mixin Post
 */
final class SpreadValueMergeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $titled = ['by' => $this->title];

        return [
            'id' => $this->id,
            $this->merge(['by' => $this->author, ...$titled]),
        ];
    }
}
