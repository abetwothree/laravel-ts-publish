<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose returned variable wraps a key in one enum's resource, then another's in a branch.
 *
 * @mixin Post
 */
class ReturnedEnumResourceResetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id, 's' => EnumResource::make($this->status)];

        if ($request->boolean('p')) {
            $data['s'] = EnumResource::make($this->priority);
        }

        return $data;
    }
}
