<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\Wrapped\CallPathWrapped;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A resource over a plain class: `$this->resource::m()`, a forwarded `$this->m()` and `$this->resource->m()`. */
final class CallPathWrappedResource extends JsonResource
{
    /** @var CallPathWrapped */
    public $resource;

    /**
     * The payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'resource_static_label' => $this->resource::staticLabel(),
            'resource_static_plain_date' => $this->resource::staticDate(),
            'resource_static_base_model' => $this->resource::staticBaseModel(),
            'forwarded_label' => $this->label(),
            'forwarded_plain_date' => $this->date(),
            'forwarded_base_model' => $this->baseModel(),
            'forwarded_auth_user' => $this->authUser(),
            'chained_plain_date' => $this->resource->date(),
            'chained_base_model' => $this->resource->baseModel(),
        ];
    }
}
