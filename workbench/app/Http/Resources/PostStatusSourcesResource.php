<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;

/**
 * Wraps the post's enums in an EnumResource reached through a local, a helper on the resource and a method that returns
 * the enum, which each publish the AsEnum type `EnumResource::make($this->status)` does.
 *
 * @mixin Post
 */
final class PostStatusSourcesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status;
        $visibility = $this->visibility;

        return [
            'status_from_local' => EnumResource::make($status),
            'visibility_from_local' => new EnumResource($visibility),
            'status_from_helper' => $this->statusResource(),
            'visibility_from_helper' => $this->visibilityResource(),
            'status_from_method' => EnumResource::make($this->currentStatus()),
        ];
    }

    /**
     * The post's status as an enum resource, wrapped through a local of the helper's own.
     */
    public function statusResource(): EnumResource
    {
        $status = $this->status;

        return EnumResource::make($status);
    }

    /**
     * The post's visibility as an enum resource, or null without one.
     */
    public function visibilityResource(): ?EnumResource
    {
        return $this->visibility === null ? null : new EnumResource($this->visibility);
    }

    /**
     * The post's status.
     */
    public function currentStatus(): Status
    {
        return $this->resource->status;
    }
}
