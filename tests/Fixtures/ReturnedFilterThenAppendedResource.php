<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose first return is a filter, beside a variable with a key the walk cannot name.
 *
 * @mixin Tag
 */
class ReturnedFilterThenAppendedResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($request->boolean('compact')) {
            return $this->only(['id', 'name']);
        }

        $data = ['id' => $this->id];
        $data[] = $this->name;

        return $data;
    }
}
