<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsIntersect;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose intersection only builds on an enum's name, beside a key that reads the enum's type.
 *
 * @mixin Post
 */
#[TsIntersect('{ s: Status }', '@/types/foo')]
class IntersectBuiltOnEnumNameResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['st' => $this->status];
    }
}
