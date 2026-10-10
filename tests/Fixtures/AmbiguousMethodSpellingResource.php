<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose toArray() cast key spells both of its backslash signatures' names.
 *
 * @mixin Post
 */
class AmbiguousMethodSpellingResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[TsCasts(['[key: `${string}\\\\r`]' => 'string'])]
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id];

        foreach (['a', 'b'] as $name) {
            $data["{$name}\\\r"] = 1;
            $data["{$name}\\\\r"] = 1;
        }

        return $data;
    }
}
