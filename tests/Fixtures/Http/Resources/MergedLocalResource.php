<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource that merges a local array.
 *
 * @mixin Tag
 */
class MergedLocalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $defaults = ['note' => 'none'];

        return [
            'id' => $this->id,
            $this->merge($defaults),
        ];
    }
}
