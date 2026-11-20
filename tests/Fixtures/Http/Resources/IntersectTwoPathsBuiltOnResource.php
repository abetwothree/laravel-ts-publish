<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsIntersect;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose two intersections only build on one name, each from its own path.
 *
 * @mixin Post
 */
#[TsIntersect('Partial<Meta>', '@/types/a')]
#[TsIntersect('Pick<Meta, "a">', '@/types/b')]
class IntersectTwoPathsBuiltOnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id];
    }
}
