<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable fills with `??=` a key a `+=` adds a `when()` under.
 *
 * @mixin Tag
 */
class ReturnedCoalescePlusWhenResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id];
        $data += ['w' => $this->when($request->boolean('c'), 1)];
        $data['w'] ??= 'none';

        return $data;
    }
}
