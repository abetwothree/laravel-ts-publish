<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\EnumWraps;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A returned variable whose branches write one key as a wrap of the status or as the bare visibility (GH-134).
 *
 * @mixin Post
 */
class WalkedWrapResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id];

        if ($this->is_pinned) {
            $data['state'] = EnumResource::make($this->status);
        } else {
            $data['state'] = $this->visibility;
        }

        return $data;
    }
}
