<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable unsets the key a parameter holds.
 *
 * @mixin Tag
 */
class ReturnedParameterKeyUnsetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request, string $k = 'slug'): array
    {
        if ($request->boolean('compact')) {
            return ['id' => $this->id, 'name' => $this->name];
        }

        $data = ['id' => $this->id, 'name' => $this->name, 'slug' => $this->slug];
        unset($data['name'], $data[$k]);

        return $data;
    }
}
