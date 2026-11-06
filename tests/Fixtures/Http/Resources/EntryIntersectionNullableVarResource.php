<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AbstractEntry;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\HasLabel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A resource that redeclares $resource as a DNF type: an abstract model that also implements an interface, or null. */
final class EntryIntersectionNullableVarResource extends JsonResource
{
    /** @var (AbstractEntry&HasLabel)|null */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'label' => $this->resource->label(),
            'proxy_label' => $this->label(),
            'id' => $this->resource->getKey(),
            'proxy_id' => $this->getKey(),
            'title' => $this->resource->title,
            'proxy_title' => $this->title,
        ];
    }
}
