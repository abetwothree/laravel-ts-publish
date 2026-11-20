<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose extends clause only builds on a name a class-level cast imports from another path.
 *
 * @mixin Post
 */
#[TsExtends('Pick<Meta, "a">', '@/types/other')]
#[TsCasts(['m' => ['type' => 'Meta', 'import' => '@/types/meta']])]
class ExtendsBesideCastNameResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'm' => $this->title];
    }
}
