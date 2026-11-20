<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose clauses name one type whole from one path and only build on it from another.
 *
 * @mixin Post
 */
#[TsExtends('Meta', '@/types/meta')]
#[TsExtends('Pick<Meta, "a">', '@/types/other')]
class ExtendsWholeBesideNestedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id];
    }
}
