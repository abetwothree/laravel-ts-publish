<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\EnumWraps;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\ValueObjects\EnumWraps\EnumWrapBox;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * Wraps and reads of the app and the CRM `Status`, two enums that share a name (GH-160, GH-76). Each arm sends its own
 * enum, so each keeps its own alias.
 *
 * @property-read EnumWrapBox $resource
 */
class SameNamedWrapsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'app_or_crm_wrap' => $this->resource->c
                ? EnumResource::make($this->resource->status)
                : EnumResource::make($this->resource->crmStatus),
            'app_or_crm_wrap_when' => $this->when(
                $this->resource->c,
                EnumResource::make($this->resource->status),
                EnumResource::make($this->resource->crmStatus),
            ),
            'app_or_crm_wrap_under_key' => ['subject' => $this->resource->c
                ? EnumResource::make($this->resource->status)
                : EnumResource::make($this->resource->crmStatus)],
            'wrap_or_two_reads' => $this->resource->c
                ? EnumResource::make($this->resource->status)
                : ($this->resource->d ? $this->resource->status : $this->resource->crmStatus),
            'crm_spread_over_wrap' => [
                'c' => EnumResource::make($this->resource->status),
                ...($this->resource->d ? ['c' => $this->resource->crmStatus] : []),
            ],
            'literals_nested_crm' => $this->resource->c
                ? ['s' => $this->resource->status, 'x' => ['c' => CrmStatus::Lead]]
                : ['s' => $this->resource->status, 'x' => null],
            'app_or_crm_wrap_or_read' => $this->resource->c
                ? EnumResource::make($this->resource->status)
                : ($this->resource->d ? EnumResource::make($this->resource->crmStatus) : $this->resource->status),
        ];
    }
}
