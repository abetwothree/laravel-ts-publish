<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable fills with `??=` a key holding a `when()` a `switch` case may unset.
 *
 * @mixin Tag
 */
class ReturnedCoalesceUnsetWhenResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id, 'w' => $this->when($request->boolean('c'), 1)];

        switch ($request->input('k')) {
            case 'u':
                unset($data['w']);

                break;
        }

        $data['w'] ??= 'none';

        return $data;
    }
}
