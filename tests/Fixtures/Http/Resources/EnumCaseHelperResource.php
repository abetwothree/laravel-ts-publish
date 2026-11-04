<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;

/**
 * Reads and wraps the plain enum case a helper with a vague declaration returns.
 *
 * @mixin Post
 */
class EnumCaseHelperResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'draft' => $this->draft(),
            'draft_resource' => EnumResource::make($this->draft()),
            'draft_list' => EnumResource::collection([$this->draft()]),
        ];
    }

    /** A case behind a mixed declaration. */
    protected function draft(): mixed
    {
        return Status::Draft;
    }
}
