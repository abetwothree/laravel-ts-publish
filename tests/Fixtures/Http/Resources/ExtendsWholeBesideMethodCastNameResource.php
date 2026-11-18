<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose extends clause is a name a method-level cast imports from another path.
 *
 * @mixin Post
 */
#[TsExtends('Meta', '@/types/other')]
class ExtendsWholeBesideMethodCastNameResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[TsCasts(['m' => ['type' => 'Meta', 'import' => '@/types/meta']])]
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'm' => $this->title];
    }
}
