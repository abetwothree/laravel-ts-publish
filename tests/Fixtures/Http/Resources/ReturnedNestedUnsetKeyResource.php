<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable unsets a key inside one of its keys.
 *
 * @mixin Tag
 */
class ReturnedNestedUnsetKeyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($request->boolean('compact')) {
            return ['id' => $this->id, 'meta' => ['name' => $this->name]];
        }

        $data = ['id' => $this->id, 'meta' => ['name' => $this->name, 'slug' => $this->slug]];
        unset($data['meta']['slug']);

        return $data;
    }
}
