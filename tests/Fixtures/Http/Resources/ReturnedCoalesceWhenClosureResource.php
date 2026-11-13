<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable fills with `??=` a key a `when()` holds a closure's null in, which
 * `when()` calls.
 *
 * @mixin Tag
 */
class ReturnedCoalesceWhenClosureResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id, 'w' => $this->when($request->boolean('c'), fn () => null)];
        $data['w'] ??= 'x';

        return $data;
    }
}
