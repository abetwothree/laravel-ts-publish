<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable fills with `??=` a key one `switch` case writes a `when()` into and
 * another fills with `??=`.
 *
 * @mixin Tag
 */
class ReturnedCoalesceSwitchWhenResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id];

        switch ($request->input('k')) {
            case 'w':
                $data['w'] = $this->when($request->boolean('c'), 1);

                break;
            case 'f':
                $data['w'] ??= 'first';

                break;
        }

        $data['w'] ??= false;

        return $data;
    }
}
