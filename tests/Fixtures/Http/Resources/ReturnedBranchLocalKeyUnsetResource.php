<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable unsets a literal key and a key a local holds inside an `if` branch.
 *
 * @mixin Tag
 */
class ReturnedBranchLocalKeyUnsetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $k = 'slug';
        $data = ['id' => $this->id, 'name' => $this->name, 'slug' => $this->slug];

        if ($request->boolean('slim')) {
            unset($data['name'], $data[$k]);
        }

        return $data;
    }
}
