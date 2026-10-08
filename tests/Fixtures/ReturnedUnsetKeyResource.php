<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable unsets a key its literal base set.
 *
 * @mixin Tag
 */
class ReturnedUnsetKeyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($request->boolean('compact')) {
            return ['id' => $this->id, 'name' => $this->name];
        }

        $data = ['id' => $this->id, 'name' => $this->name, 'slug' => $this->slug];
        unset($data['name']);

        return $data;
    }
}
