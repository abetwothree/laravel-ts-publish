<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Optional casts on two index signatures, one on the class and one on toArray(): neither can carry `?:`.
 *
 * @mixin Post
 */
#[TsCasts(['[key: `${string}_tag`]' => ['type' => 'string', 'optional' => true]])]
final class OptionalSignatureCastResource extends JsonResource
{
    #[TsCasts(['[key: `${string}_note`]' => ['type' => 'number', 'optional' => true]])]
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id];

        foreach (['east', 'west'] as $name) {
            $data["{$name}_tag"] = $this->title;
            $data["{$name}_note"] = $this->word_count;
        }

        return $data;
    }
}
