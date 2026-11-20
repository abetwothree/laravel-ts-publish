<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use AbeTwoThree\LaravelTsPublish\Attributes\TsIntersect;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose extends clause and intersection each import a name of their own path.
 *
 * @mixin Post
 */
#[TsExtends('Base', '@/a')]
#[TsIntersect('Base', '@/b')]
class ExtendsAndIntersectTwoPathsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id];
    }
}
