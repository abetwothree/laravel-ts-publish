<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose two returned literals wrap a key in different enums' resources.
 *
 * @mixin Post
 */
class ReturnedEnumResourceBranchesResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($request->boolean('p')) {
            return ['id' => $this->id, 's' => EnumResource::make($this->priority)];
        }

        return ['id' => $this->id, 's' => EnumResource::make($this->status)];
    }
}
