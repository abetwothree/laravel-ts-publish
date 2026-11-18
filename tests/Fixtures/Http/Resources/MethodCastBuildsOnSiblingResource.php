<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose method-level casts build on a resource and an enum other keys send.
 *
 * @mixin Post
 */
class MethodCastBuildsOnSiblingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[TsCasts([
        'k' => ['type' => 'Paginated<UserResource>', 'import' => '@/types/meta'],
        'e' => ['type' => 'Record<string, StatusType>', 'import' => '@/types/meta'],
    ])]
    public function toArray(Request $request): array
    {
        return [
            'k' => $this->title,
            'e' => $this->title,
            'other' => new UserResource($this->author),
            'st' => $this->status,
        ];
    }
}
