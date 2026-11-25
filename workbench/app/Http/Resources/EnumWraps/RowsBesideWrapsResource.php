<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\EnumWraps;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\EnumWraps\EnumWrapBox;
use Workbench\App\ValueObjects\EnumWraps\EnumWrapRow;

/**
 * Rows of the app and the CRM `Status`, or of names, in a ternary before two or three enum wraps, and a `null` first
 * arm (GH-160). The rows send each enum's value, a wrap its object.
 *
 * @property-read EnumWrapBox $resource
 */
class RowsBesideWrapsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'rows_then_two_wraps' => $this->resource->pick === 'x'
                ? array_map(fn (EnumWrapRow $r) => ['c' => $r->crmStatus, 's' => $r->status], $this->resource->rows)
                : ($this->resource->pick === 's'
                    ? EnumResource::make($this->resource->status)
                    : EnumResource::make($this->resource->role)),
            'rows_then_three_wraps' => $this->resource->pick === 'x'
                ? array_map(fn (EnumWrapRow $r) => ['c' => $r->crmStatus, 's' => $r->status], $this->resource->rows)
                : ($this->resource->pick === 's'
                    ? EnumResource::make($this->resource->status)
                    : ($this->resource->pick === 'r'
                        ? EnumResource::make($this->resource->role)
                        : EnumResource::make($this->resource->visibility))),
            'named_rows_then_two_wraps' => $this->resource->pick === 'x'
                ? array_map(fn (EnumWrapRow $r) => ['n' => $r->name], $this->resource->rows)
                : ($this->resource->pick === 's'
                    ? EnumResource::make($this->resource->status)
                    : EnumResource::make($this->resource->role)),
            'null_then_two_wraps' => $this->resource->pick === 'n'
                ? null
                : ($this->resource->pick === 's'
                    ? EnumResource::make($this->resource->status)
                    : EnumResource::make($this->resource->role)),
        ];
    }
}
