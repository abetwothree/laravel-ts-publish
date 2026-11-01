<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable fills with `??=` a key written from a local holding a `when()`.
 *
 * @mixin Tag
 */
class ReturnedCoalesceWhenLocalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $w = $this->when($request->boolean('c'), 1);
        $data = ['id' => $this->id];
        $data['w'] = $w;
        $data['w'] ??= 'none';

        return $data;
    }
}
