<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose returned variable spreads itself to re-set an enum-cast column to a string.
 *
 * @mixin Post
 */
class ReturnedEnumSpreadResetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $d = ['id' => $this->id, 'status' => $this->status];
        $d = [...$d, 'status' => 'archived'];

        return $d;
    }
}
