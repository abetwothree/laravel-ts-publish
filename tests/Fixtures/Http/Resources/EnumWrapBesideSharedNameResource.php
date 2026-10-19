<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource whose unions put an enum resource beside reads of two enums that share a name, top level and
 * nested beside sibling reads, and the same nesting with no enum resource.
 *
 * @mixin Warehouse
 */
class EnumWrapBesideSharedNameResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'mixed' => match (true) {
                $this->id > 1 => EnumResource::make($this->status),
                $this->id > 0 => $this->status,
                default => $this->current_crm_status,
            },
            'nested_mixed' => [
                'm' => match (true) {
                    $this->id > 1 => EnumResource::make($this->status),
                    $this->id > 0 => $this->status,
                    default => $this->current_crm_status,
                },
                'n' => $this->current_crm_status,
                'o' => $this->status,
            ],
            'nested_reads' => [
                'm' => $this->id > 0 ? $this->status : $this->current_crm_status,
                'n' => $this->current_crm_status,
                'o' => $this->status,
            ],
        ];
    }
}
