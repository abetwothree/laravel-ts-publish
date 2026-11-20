<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose clause, aliased apart from another, declares a type parameter of the same name.
 *
 * @mixin Post
 */
#[TsExtends('Record<string, { f: <Meta>(x: Meta) => Meta; g: Meta }>', '@/types/a', ['Meta'])]
#[TsExtends('Meta', '@/types/b')]
class AliasShadowedByTypeParameterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id];
    }
}
