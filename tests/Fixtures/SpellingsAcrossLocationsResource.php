<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource that casts its `\_x` signature on the class by the exact name, and on toArray() and casts() by
 * the single-backslash paste. casts() outranks the class, and the class outranks toArray(), whatever the spellings.
 *
 * @mixin Post
 */
#[TsCasts(['[key: `${string}\\\\_x`]' => 'string'])]
class SpellingsAcrossLocationsResource extends JsonResource
{
    /** @return array<string, string> */
    #[TsCasts(['[key: `${string}\\_x`]' => 'number'])]
    public function casts(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    #[TsCasts(['[key: `${string}\\_x`]' => 'boolean'])]
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id];

        foreach (['a', 'b'] as $name) {
            $data["{$name}\\_x"] = 'x';
        }

        return $data;
    }
}
