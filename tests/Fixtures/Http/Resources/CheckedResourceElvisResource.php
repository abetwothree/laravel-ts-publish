<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\UserResource;

/** A test-only resource whose short ternaries check a resource they build, which filter() sends as null around null. */
class CheckedResourceElvisResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'made' => EnumResource::make(Status::Draft) ?: Status::Published,
            'built' => new EnumResource(Status::Draft) ?: null,
            'tryFrom' => EnumResource::make(Status::tryFrom($request->integer('s', 9))) ?: null,
            'user' => UserResource::make($request->input('u')) ?: null,
        ];
    }
}
