<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Lays a cast that spells an enum's own type over each key that holds an enum resource: each key publishes its cast
 * and imports the enum types it spells, never the `AsEnum` wrap.
 *
 * @mixin Post
 */
#[TsCasts([
    'status' => 'StatusType',
    'either' => 'StatusType | VisibilityType | null',
    'mixed' => 'StatusType | null',
])]
class PostSpelledCastResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[TsCasts(['visibility' => 'VisibilityType'])]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => EnumResource::make($this->status),
            'visibility' => EnumResource::make($this->visibility),
            'either' => $request->boolean('status') ? EnumResource::make($this->status) : EnumResource::make($this->visibility),
            'mixed' => $request->boolean('status') ? EnumResource::make($this->status) : $this->status,
        ];
    }
}
