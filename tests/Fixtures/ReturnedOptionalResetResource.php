<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable starts a key with a value that can vanish, then re-sets it on one path.
 *
 * @mixin Tag
 */
class ReturnedOptionalResetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $d = ['a' => $this->whenLoaded('posts')];

        if ($request->boolean('x')) {
            $d['a'] = 1;
        }

        return $d;
    }
}
