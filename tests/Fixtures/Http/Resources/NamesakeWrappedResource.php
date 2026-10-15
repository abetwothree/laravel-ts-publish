<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\NamesakeInvoice;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\NamesakeStatusCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A resource over a wrapped class whose methods share names with the backing model's.
 *
 * @mixin NamesakeInvoice
 */
final class NamesakeWrappedResource extends JsonResource
{
    /** @var NamesakeStatusCard */
    public $resource;

    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status(),
            'status_via_resource' => $this->resource->status(),
            'kind' => $this->resource::kind(),
            'reference' => $this->reference(),
            'total' => $this->total(),
            'opaque' => $this->opaque(),
            'opaque_via_resource' => $this->resource->opaque(),
        ];
    }
}
