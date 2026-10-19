<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\NamesakeInvoice;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\NamesakeStatusCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A resource whose wrapped class declares a protected method the backing model declares public.
 *
 * @mixin NamesakeInvoice
 */
final class NamesakeProtectedResource extends JsonResource
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
            'first' => $this->isFirst(),
            'first_via_resource' => $this->resource->isFirst(),
        ];
    }
}
