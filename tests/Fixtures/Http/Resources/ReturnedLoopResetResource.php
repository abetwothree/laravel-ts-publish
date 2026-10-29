<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Category;

/**
 * A test-only resource whose returned variable re-sets a key in a loop, which may run zero times.
 *
 * @mixin Category
 */
class ReturnedLoopResetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id, 'mode' => 1];

        foreach ($this->children as $child) {
            $data['mode'] = 'nested';
            $data['child'] = $child->id;
        }

        return $data;
    }
}
