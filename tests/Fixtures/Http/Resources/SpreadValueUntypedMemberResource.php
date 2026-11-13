<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource spreading a local whose untyped member writes over a typed key.
 *
 * @mixin Post
 */
final class SpreadValueUntypedMemberResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $untyped = ['by' => json_decode('{"a":1}')];

        return ['by' => $this->author, ...$untyped, 'row' => ['by' => 'anon', ...$untyped]];
    }
}
