<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose extends clause spells `AsEnum` and imports from a path, beside a cast needing a const.
 *
 * @mixin Post
 */
#[TsExtends('Partial<Record<"hint", AsEnum<typeof Status>>>', '@/types/x')]
#[TsCasts(['k' => '(typeof Status)[keyof typeof Status]'])]
class ExtendsAsEnumFromPathBesideTypeofCastResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'k' => EnumResource::make($this->status),
        ];
    }
}
