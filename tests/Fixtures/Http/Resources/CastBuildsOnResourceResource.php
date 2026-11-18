<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose class-level casts build on the resource each key sends, beside names only their imports
 * bring.
 *
 * @mixin Post
 */
#[TsCasts([
    'mixed' => ['type' => '{ owner: UserResource; meta: Meta }', 'import' => '@/types/meta'],
    'paginated' => ['type' => 'Paginated<UserResource>', 'import' => '@/types/paginated'],
])]
class CastBuildsOnResourceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'mixed' => new UserResource($this->author),
            'paginated' => new UserResource($this->author),
        ];
    }
}
