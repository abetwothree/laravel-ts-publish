<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose lone returned variable has a key the walk cannot name, under method-level casts.
 *
 * @mixin Tag
 */
class ReturnedCastLoneVariableResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[TsCasts(['meta' => 'Record<string, string>', 'injected' => 'number'])]
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
