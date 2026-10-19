<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable sets a key to a value read only partly, its own key set complete.
 *
 * @mixin Tag
 */
class ReturnedPartialValueResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($request->boolean('compact')) {
            return ['id' => $this->id, 'name' => $this->name];
        }

        $data = ['id' => $this->id, 'name' => $this->name];
        $data['meta'] = [...$this->opaque(), 'kind' => 'tag'];

        return $data;
    }

    /** @return array<string, mixed> */
    protected function opaque(): array
    {
        return $this->resource->toArray();
    }
}
