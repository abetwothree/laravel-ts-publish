<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose merge closure assigns its own local, named as one of the method's, and merges its value.
 *
 * @mixin Tag
 */
class MergeClosureShadowedValueResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $label = 'x';

        return [
            'id' => $this->id,
            'outer' => $label,
            $this->merge(function () {
                $label = $this->id;

                return ['label' => $label];
            }),
        ];
    }
}
