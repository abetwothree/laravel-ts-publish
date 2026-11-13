<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable fills with `??=` a key a `mergeWhen()` merges in only once filtered.
 *
 * @mixin Tag
 */
class ReturnedCoalesceMergeWhenResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id, $this->mergeWhen($request->boolean('c'), ['w' => 1])];
        $data['w'] ??= 'none';

        return $data;
    }
}
