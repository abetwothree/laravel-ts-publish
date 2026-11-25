<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\EnumWraps;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\AddressResource;
use Workbench\App\ValueObjects\EnumWraps\EnumWrapBox;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * Lists array_filter() keeps of the app's and the CRM's `Status`, or of the `Address` model and its resource, each
 * beside a later token of that name (GH-76, GH-160). The resource re-indexes each filtered list, so every token after
 * one still names its own class.
 *
 * @property-read EnumWrapBox $resource
 */
class FilteredSameNamedListsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $reads = array_filter([CrmStatus::Lead, Status::Draft]);
        $wraps = array_filter([EnumResource::make(CrmStatus::Lead), EnumResource::make(Status::Draft)]);
        $addresses = array_filter([new AddressResource($this->resource->address), $this->resource->address]);
        $mixed = array_filter([
            new AddressResource($this->resource->address),
            EnumResource::make(CrmStatus::Lead),
            $this->resource->address,
            EnumResource::make(Status::Draft),
        ]);

        if ($this->resource->d) {
            $reads = [Status::Draft];
            $wraps = [EnumResource::make(Status::Draft)];
            $addresses = [$this->resource->address];
            $mixed = [EnumResource::make(Status::Draft)];
        }

        return [
            'either' => $this->resource->c ? array_filter([CrmStatus::Lead, Status::Draft]) : [Status::Draft],
            'app_after' => ['list' => array_filter([CrmStatus::Lead, Status::Draft]), 'one' => Status::Draft],
            'crm_after' => ['list' => array_filter([Status::Draft, CrmStatus::Lead]), 'one' => CrmStatus::Lead],
            'model_after' => [
                'list' => array_filter([new AddressResource($this->resource->address), $this->resource->address]),
                'one' => $this->resource->address,
            ],
            'wrap_after' => [
                'list' => array_filter([EnumResource::make(CrmStatus::Lead), EnumResource::make(Status::Draft)]),
                'one' => EnumResource::make(Status::Draft),
            ],
            'reads' => $reads,
            'wraps' => $wraps,
            'addresses' => $addresses,
            'mixed' => $mixed,
        ];
    }
}
