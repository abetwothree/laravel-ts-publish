<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable fills with `??=` a key holding a `when()` that a branch may set again.
 *
 * @mixin Tag
 */
class ReturnedCoalesceWhenBranchSetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id, 'w' => $this->when($request->boolean('c'), 1)];

        if ($request->boolean('d')) {
            $data['w'] = 2;
        }

        $data['w'] ??= 'none';

        return $data;
    }
}
