<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Attributes\TsIntersect;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose intersection builds on a name, inside a union inside an object, that a class-level cast
 * imports from another path.
 *
 * @mixin Post
 */
#[TsIntersect('{ at: Meta | Other | null }', '@/types/meta')]
#[TsCasts(['m' => ['type' => 'Other', 'import' => '@/types/o']])]
class IntersectNestedArmBesideCastNameResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'm' => $this->title];
    }
}
