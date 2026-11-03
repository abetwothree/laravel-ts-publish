<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Names its wrapped class in a `@var` on `$resource` with no import: the class sits in this class's own namespace. */
final class InvoiceViewResource extends JsonResource
{
    /** @var InvoiceView */
    public $resource;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->resource->number,
            'total' => $this->resource->total,
            'paid' => $this->resource->paid,
        ];
    }
}
