<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources\EnumWraps;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\MediaType;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\App\ValueObjects\EnumWraps\EnumWrapBox;

/**
 * An enum wrap beside a bare read, a case, a `->value` or `->name` read, a literal or an object holding a read of the
 * enum (GH-160, GH-108). The wrap sends its object, every other arm what it reads.
 *
 * @property-read EnumWrapBox $resource
 */
class WrapBesideReadResource extends JsonResource
{
    private const array PLAINS = [Status::Draft, Status::Published];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'wrap_or_helper_offset' => $this->resource->c
                ? EnumResource::make($this->resource->kind)
                : $this->untypedHelper()['k'],
            'wrap_or_nested_value' => $this->resource->c
                ? EnumResource::make($this->resource->kind)
                : ($this->resource->d ? $this->resource->kind->value : 'x'),
            'wrap_or_nested_nullable_value' => $this->resource->c
                ? EnumResource::make($this->resource->kind)
                : ($this->resource->d ? $this->resource->kind->value : $this->resource->maybeKind?->value),
            'match_wrap_read_literal' => match (true) {
                $this->resource->c => EnumResource::make($this->resource->status),
                $this->resource->d => $this->resource->status,
                default => 'none',
            },
            'match_wrap_value_name' => match ($this->resource->pick) {
                'w' => new EnumResource($this->resource->status),
                'v' => $this->resource->status->value,
                default => $this->resource->status->name,
            },
            'match_wrap_value_other' => match ($this->resource->pick) {
                'w' => new EnumResource($this->resource->status),
                'v' => $this->resource->status->value,
                default => 'other',
            },
            'nested_make_or_collection_or_read' => $this->resource->c
                ? match ($this->resource->pick) {
                    'm' => EnumResource::make($this->resource->status),
                    default => EnumResource::collection([$this->resource->status]),
                }
                : $this->resource->status,
            'collection_or_plain_list' => $this->resource->c ? EnumResource::collection(self::PLAINS) : self::PLAINS,
            'wrap_or_other_enum_case' => $this->resource->c ? EnumResource::make(Role::Admin) : Status::Published,
            'wrap_or_nested_nullable_case' => $this->resource->c
                ? EnumResource::make(Status::Draft)
                : ($this->resource->d ? Status::Published : null),
            'wrap_or_object_value' => $this->resource->c
                ? EnumResource::make($this->resource->kind)
                : ['v' => $this->resource->kind->value],
        ];
    }

    /**
     * A case's value or the kind's value, under a key no return type names.
     */
    private function untypedHelper()
    {
        return ['k' => $this->resource->c ? MediaType::Image->value : $this->resource->kind->value];
    }
}
