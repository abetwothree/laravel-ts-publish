<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\EnumWraps;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\EnumWraps\EnumWrapBox;
use Workbench\App\ValueObjects\EnumWraps\EnumWrapRow;

/**
 * Rows keyed by name and mapped to the app's and the CRM's `Status` (GH-160, GH-76): wrapped, read, and in an object.
 * The list arm and the Record arm name each enum alike.
 *
 * @property-read EnumWrapBox $resource
 */
class KeyedSameNamedMapsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'wraps' => collect($this->resource->rows)->keyBy('name')->map(fn (EnumWrapRow $row) => $this->resource->c
                ? EnumResource::make($row->status)
                : EnumResource::make($row->crmStatus)),
            'reads' => collect($this->resource->rows)->keyBy('name')
                ->map(fn (EnumWrapRow $row) => $this->resource->c ? $row->status : $row->crmStatus),
            'objects' => collect($this->resource->rows)->keyBy('name')
                ->map(fn (EnumWrapRow $row) => ['app' => $row->status, 'crm' => $row->crmStatus]),
        ];
    }
}
