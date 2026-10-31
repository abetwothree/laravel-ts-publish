<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;

/** A test-only resource whose short ternaries check a resource they build, which PHP always reads as true. */
class CheckedResourceElvisResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'made' => EnumResource::make(Status::Draft) ?: Status::Published,
            'collected' => EnumResource::collection(Status::cases()) ?: [],
            'built' => new EnumResource(Status::Draft) ?: null,
        ];
    }
}
