<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\Discriminated;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\UserResource;
use Workbench\App\Models\Post;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * Keys a boolean the resource sends picks, whose arms need imports: an enum resource of two enums, two same-named
 * enums and a resource only one arm sends (GH-157).
 *
 * @mixin Post
 */
class PinnedWrapsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'is_pinned' => $this->is_pinned,
            'state' => $this->is_pinned ? EnumResource::make($this->status) : EnumResource::make($this->visibility),
            'stage' => $this->is_pinned ? $this->status : CrmStatus::Lead,
            'author' => $this->when($this->is_pinned, fn () => new UserResource($this->user)),
        ];
    }
}
