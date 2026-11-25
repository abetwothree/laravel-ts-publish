<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Support\Opaque;

/**
 * A test-only resource whose nested literal splits on `pinned` beside a key holding an arm no rule types, which the
 * `@return` fills in place.
 *
 * @mixin Post
 */
final class VariantGapReturnResource extends JsonResource
{
    /**
     * @return array{nested: array{pinned: bool, v: int|string, other: int|null}}
     */
    public function toArray(Request $request): array
    {
        return [
            'nested' => [
                'pinned' => $this->is_pinned,
                'v' => $this->is_pinned ? 1 : 'x',
                'other' => $this->id > 1 ? Opaque::value(1) : null,
            ],
        ];
    }
}
