<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A resource the convention backs with BuiltPost, redeclaring `$resource` as an interface every model implements. */
final class BuiltPostResource extends JsonResource
{
    /** @var Arrayable */
    public $resource;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['title' => $this->title, 'whole' => $this->resource];
    }
}
