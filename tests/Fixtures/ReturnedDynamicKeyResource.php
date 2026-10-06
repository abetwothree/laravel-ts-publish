<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable sets a key the walk cannot name, from a loop over key names.
 *
 * @mixin Tag
 */
class ReturnedDynamicKeyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($request->boolean('compact')) {
            return ['id' => $this->id, 'name' => $this->name];
        }

        $data = ['id' => $this->id];

        foreach (['name'] as $key) {
            $data[$key] = $this->resource->getAttribute($key);
        }

        return $data;
    }
}
