<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable fills two keys with `??=`: one a `when()` leaves out, which `??=` keeps,
 * and one only a branch writes.
 *
 * @mixin Tag
 */
class ReturnedCoalesceWhenResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id, 'w' => $this->when($request->boolean('c'), 1)];

        if ($request->boolean('c')) {
            $data['a'] = 1;
        }

        $data['w'] ??= 'none';
        $data['a'] ??= 'none';

        return $data;
    }
}
