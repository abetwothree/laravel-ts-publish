<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable spreads another resource's resolve(), beside a literal: that resource
 * returns alone a variable with a key the walk cannot name, so its own read is partial.
 *
 * @mixin Tag
 */
class ReturnedPartlyReadSpreadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($request->boolean('compact')) {
            return ['id' => $this->id, 'extra' => 1];
        }

        $data = [...ReturnedDynamicKeyAloneResource::make($this->resource)->resolve($request)];

        return $data;
    }
}
