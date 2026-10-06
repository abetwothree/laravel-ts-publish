<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose returned variable re-sets an enum-cast column to a string by a key write.
 *
 * @mixin Post
 */
class ReturnedEnumKeyResetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $d = ['id' => $this->id, 'status' => $this->status];
        $d['status'] = 'archived';

        return $d;
    }
}
