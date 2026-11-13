<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose untyped key a spread may write over.
 *
 * @mixin Post
 */
final class SpreadValueUntypedHeldResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $maybe = [];

        if ($request->boolean('titled')) {
            $maybe['by'] = $this->title;
        }

        return ['by' => json_decode('{"a":1}'), ...$maybe, 'row' => ['by' => json_decode('{"a":1}'), ...$maybe]];
    }
}
