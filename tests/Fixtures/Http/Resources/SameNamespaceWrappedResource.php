<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Names its model in a `@var` on `$resource` with no import: the model sits in this class's own namespace. */
final class SameNamespaceWrappedResource extends JsonResource
{
    /** @var SameNamespacePost|null */
    public $resource;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['title' => $this->title];
    }
}
