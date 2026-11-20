<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use AbeTwoThree\LaravelTsPublish\Attributes\TsIntersect;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose extends clause and intersection each list a name of their own path in their `types`.
 *
 * @mixin Post
 */
#[TsExtends('Base', '@/a', ['Base'])]
#[TsIntersect('Partial<Base>', '@/b', ['Base'])]
class ExtendsAndIntersectTypesTwoPathsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id];
    }
}
