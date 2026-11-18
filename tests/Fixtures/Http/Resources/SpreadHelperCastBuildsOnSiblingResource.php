<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource spreading a helper whose cast builds on the resource the outer literal sends.
 *
 * @mixin Post
 */
class SpreadHelperCastBuildsOnSiblingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [...$this->head(), 'other' => new UserResource($this->author)];
    }

    /**
     * The key the helper casts.
     *
     * @return array<string, mixed>
     */
    #[TsCasts(['k' => ['type' => 'Paginated<UserResource>', 'import' => '@/types/meta']])]
    private function head(): array
    {
        return ['k' => $this->title];
    }
}
