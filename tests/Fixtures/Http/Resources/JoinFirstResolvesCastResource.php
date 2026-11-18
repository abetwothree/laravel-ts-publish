<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose branches each spread a helper casting one key alike, only one sending the resource the
 * cast builds on; its twin joins the branches the other way round.
 *
 * @mixin Post
 */
class JoinFirstResolvesCastResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->is_pinned) {
            return [...$this->head2(), 'id' => $this->id];
        }

        return [...$this->head(), 'id' => $this->id];
    }

    /**
     * The key alone.
     *
     * @return array<string, mixed>
     */
    #[TsCasts(['k' => ['type' => 'Paginated<UserResource>', 'import' => '@/types/meta']])]
    private function head(): array
    {
        return ['k' => $this->title];
    }

    /**
     * The key beside the resource it builds on.
     *
     * @return array<string, mixed>
     */
    #[TsCasts(['k' => ['type' => 'Paginated<UserResource>', 'import' => '@/types/meta']])]
    private function head2(): array
    {
        return ['k' => $this->title, 'other' => new UserResource($this->author)];
    }
}
