<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use AbeTwoThree\LaravelTsPublish\Attributes\TsIntersect;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose clauses build on a resource's name no key sends, each from its own path.
 *
 * @mixin Post
 */
#[TsExtends('Pick<UserResource, "id">', '@/types/a')]
#[TsIntersect('Partial<UserResource>', '@/types/b')]
class BuiltOnUnsentResourceTwoPathsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id];
    }
}
