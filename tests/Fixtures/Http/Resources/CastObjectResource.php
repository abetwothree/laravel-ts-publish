<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Sends numeric keys in an object a cast builds, which the response keeps, beside the same keys in an array, which it
 * re-indexes into a list.
 *
 * @mixin Post
 */
class CastObjectResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'codes' => (object) [10 => 'a', 20 => 'b'],
            'list' => [10 => 'a', 20 => 'b'],
        ];
    }
}
