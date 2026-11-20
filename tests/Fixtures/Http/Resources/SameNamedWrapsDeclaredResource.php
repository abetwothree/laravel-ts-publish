<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\WrapPicks;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * Wraps the app's or the CRM's status under a `@return` naming both, alone, beside a value only the `@return` types,
 * and beside rows it types with the app's status bare (GH-160).
 *
 * @property-read WrapPicks $resource
 */
final class SameNamedWrapsDeclaredResource extends JsonResource
{
    /** @return array{subject: Status|CrmStatus, gap: Status|CrmStatus, rows: Status|CrmStatus|list<array{s: Status}>} */
    public function toArray(Request $request): array
    {
        return [
            'subject' => $this->resource->c
                ? EnumResource::make($this->resource->status)
                : EnumResource::make($this->resource->crmStatus),
            'gap' => $this->resource->c
                ? EnumResource::make($this->resource->status)
                : ($this->resource->d ? EnumResource::make($this->resource->crmStatus) : cache('x')),
            'rows' => $this->resource->c
                ? EnumResource::make($this->resource->status)
                : ($this->resource->d
                    ? EnumResource::make($this->resource->crmStatus)
                    : array_map(fn ($r) => ['s' => cache('s')], $this->resource->rows)),
        ];
    }
}
