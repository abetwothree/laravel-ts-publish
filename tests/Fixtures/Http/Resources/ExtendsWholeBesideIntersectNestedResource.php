<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use AbeTwoThree\LaravelTsPublish\Attributes\TsIntersect;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose extends clause names a type whole from one path, and whose intersection only builds on
 * it from another.
 *
 * @mixin Post
 */
#[TsExtends('Meta', '@/types/meta')]
#[TsIntersect('Pick<Meta, "a">', '@/types/other')]
class ExtendsWholeBesideIntersectNestedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id];
    }
}
