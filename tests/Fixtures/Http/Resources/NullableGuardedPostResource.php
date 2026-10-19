<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/** Redeclares `$resource` as possibly null, and returns early unless it holds a post. */
final class NullableGuardedPostResource extends JsonResource
{
    /** @var Post|null */
    public $resource;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof Post) {
            return [];
        }

        return ['guarded' => $this->resource];
    }
}
