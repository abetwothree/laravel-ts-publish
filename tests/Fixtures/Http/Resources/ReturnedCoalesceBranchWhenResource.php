<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable fills with `??=` a key only a branch writes a `when()` into.
 *
 * @mixin Tag
 */
class ReturnedCoalesceBranchWhenResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id];

        if ($request->boolean('c')) {
            $data['w'] = $this->when($request->boolean('d'), 1);
        }

        $data['w'] ??= 'none';

        return $data;
    }
}
