<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsIntersect;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose intersection builds on the resource a key sends, with an import and no `types`.
 *
 * @mixin Post
 */
#[TsIntersect('Pick<UserResource, "id">', '@/types/user')]
class IntersectedPackageNameResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['owner' => new UserResource($this->author)];
    }
}
