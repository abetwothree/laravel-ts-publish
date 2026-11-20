<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\EnumWraps;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\EnumWraps\EnumWrapBox;

/**
 * Two or more enum wraps below a top-level key, in a list, beside an object arm holding a wrap, and through `when()`
 * (GH-160, GH-108). Each wrap sends its enum resource's object.
 *
 * @property-read EnumWrapBox $resource
 */
class NestedWrapsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'under_key' => ['subject' => $this->resource->c
                ? EnumResource::make($this->resource->status)
                : EnumResource::make($this->resource->role)],
            'in_list' => [$this->resource->c
                ? EnumResource::make($this->resource->status)
                : EnumResource::make($this->resource->role)],
            'two_levels' => ['outer' => ['inner' => $this->resource->c
                ? EnumResource::make($this->resource->status)
                : EnumResource::make($this->resource->role)]],
            'three_under_key' => ['subject' => $this->resource->pick === 's'
                ? EnumResource::make($this->resource->status)
                : ($this->resource->pick === 'r'
                    ? EnumResource::make($this->resource->role)
                    : EnumResource::make($this->resource->visibility))],
            'three_top' => $this->resource->pick === 'r'
                ? EnumResource::make($this->resource->role)
                : ($this->resource->pick === 's'
                    ? EnumResource::make($this->resource->status)
                    : EnumResource::make($this->resource->visibility)),
            'three_wraps_two_enums' => $this->resource->c
                ? EnumResource::make($this->resource->status)
                : ($this->resource->d
                    ? EnumResource::make($this->resource->priority)
                    : ($this->resource->e ? EnumResource::make($this->resource->status) : null)),
            'wrap_or_object_wrap' => $this->resource->c
                ? EnumResource::make($this->resource->status)
                : ['inner' => EnumResource::make($this->resource->visibility)],
            'when_wrap_or_object_wrap' => $this->when(
                $this->resource->c,
                EnumResource::make($this->resource->status),
                ['inner' => EnumResource::make($this->resource->visibility)],
            ),
            'when_two_wraps_one_nullable' => $this->when(
                $this->resource->c,
                EnumResource::make($this->resource->status),
                EnumResource::make($this->resource->maybeKind),
            ),
        ];
    }
}
