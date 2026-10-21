<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose values are locals built by key writes and `+=`, one read before a later write.
 *
 * @mixin Tag
 */
class KeyBuiltLocalValueResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $plus = ['a' => 1];
        $plus += ['b' => 'x'];
        $meta = ['id' => $this->id];
        $meta['name'] = $this->name;
        $early = ['meta' => $meta];
        $meta['late'] = true;

        return ['plus' => $plus, 'meta' => $meta, 'early' => $early];
    }
}
