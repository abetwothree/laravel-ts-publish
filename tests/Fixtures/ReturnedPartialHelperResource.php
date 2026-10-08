<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable starts from a helper that builds on the model's own toArray().
 *
 * @mixin Tag
 */
class ReturnedPartialHelperResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($request->boolean('compact')) {
            return ['id' => $this->id, 'name' => $this->name];
        }

        $data = $this->basics();
        $data['extra'] = true;

        return $data;
    }

    /** @return array<string, mixed> */
    protected function basics(): array
    {
        $base = $this->resource->toArray();
        $base['kind'] = 'tag';

        return $base;
    }
}
