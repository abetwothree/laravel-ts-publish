<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource returning alone a variable that sets a key the walk cannot name, from a loop over key names.
 *
 * @mixin Tag
 */
class ReturnedDynamicKeyAloneResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id];

        foreach (['name'] as $key) {
            $data[$key] = $this->resource->getAttribute($key);
        }

        return $data;
    }
}
