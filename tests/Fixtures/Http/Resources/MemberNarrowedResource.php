<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Image;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * An `instanceof` ternary on a `$this->prop` morphTo, whose proven arm reads through the same property.
 *
 * @mixin Image
 */
final class MemberNarrowedResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->imageable instanceof Post ? $this->imageable->title : null,
            'key' => $this->imageable instanceof Post ? $this->imageable->getKey() : null,
            'either_key' => $this->imageable instanceof Post || $this->imageable instanceof User ? $this->imageable->getKey() : null,
            'open_key' => $this->imageable?->getKey(),
        ];
    }
}
