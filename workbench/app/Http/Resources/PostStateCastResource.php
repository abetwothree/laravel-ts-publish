<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Lays a cast over each key that holds an enum resource: each key publishes its cast, and imports an enum only where
 * the cast writes its wrap.
 *
 * @mixin Post
 */
#[TsCasts([
    'status' => 'string',
    'either' => 'string | null',
    'held' => 'string | null',
    'wrapped' => 'AsEnum<typeof Status> | AsEnum<typeof Visibility> | null',
])]
class PostStateCastResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[TsCasts(['visibility' => 'string'])]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => EnumResource::make($this->status),
            'visibility' => EnumResource::make($this->visibility),
            'either' => $request->boolean('status') ? EnumResource::make($this->status) : EnumResource::make($this->visibility),
            'held' => $this->whenNull($this->status, EnumResource::make($this->visibility)),
            'wrapped' => $request->boolean('status') ? EnumResource::make($this->status) : EnumResource::make($this->visibility),
        ];
    }
}
