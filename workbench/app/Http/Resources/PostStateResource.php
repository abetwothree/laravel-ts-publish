<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Publishes a union of two enum resources and no enum resource of its own: the union brings the file's enum imports.
 *
 * @mixin Post
 */
class PostStateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'either' => $request->boolean('status') ? EnumResource::make($this->status) : EnumResource::make($this->visibility),
        ];
    }
}
