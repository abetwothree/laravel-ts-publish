<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose lone returned variable has a key the walk cannot name, under a `@return` shape.
 *
 * @mixin Tag
 */
class ReturnedShapedLoneVariableResource extends JsonResource
{
    /** @return array{id: int, meta: string} */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id];
        $data['meta'] = $this->resource->getAttribute('x');

        foreach (['k'] as $k) {
            $data[$k] = 1;
        }

        return $data;
    }
}
