<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource passing each conditional helper an explicit default before an unpack, beside the same call
 * without one.
 *
 * @mixin Post
 */
class UnpackedDefaultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $extra = [];

        return [
            'when_unpack' => $this->when($this->is_pinned, 1, 'off', ...$extra),
            'when_ctl' => $this->when($this->is_pinned, 1, 'off'),
            'when_no_default_unpack' => $this->when($this->is_pinned, 1, ...$extra),
            'unless_unpack' => $this->unless($this->is_pinned, 1, 'off', ...$extra),
            'loaded_unpack' => $this->whenLoaded('author', fn () => 1, 'none', ...$extra),
            'has_unpack' => $this->whenHas('title', 1, 'none', ...$extra),
            'not_null_unpack' => $this->whenNotNull($this->is_pinned ? 'set' : null, 0, ...$extra),
            'null_unpack' => $this->whenNull($this->is_pinned ? 'set' : null, 0, ...$extra),
            'counted_unpack' => $this->whenCounted('comments', fn () => 1, 'none', ...$extra),
            'appended_unpack' => $this->whenAppended('title', 1, 'none', ...$extra),
            'appended_ctl' => $this->whenAppended('title', 1, 'none'),
            'transform_unpack' => $this->transform($this->title, fn () => 1, 'none', ...$extra),
            'transform_ctl' => $this->transform($this->title, fn () => 1, 'none'),
            'split_when_unpack' => [
                'flag' => $this->is_pinned,
                'v' => $this->when($this->is_pinned, 1, 'off', ...$extra),
            ],
            'split_when_ctl' => [
                'flag' => $this->is_pinned,
                'v' => $this->when($this->is_pinned, 1, 'off'),
            ],
            'split_unless_unpack' => [
                'flag' => $this->is_pinned,
                'v' => $this->unless($this->is_pinned, 1, 'off', ...$extra),
            ],
            'merged_unpack' => [
                'flag' => $this->is_pinned,
                $this->mergeWhen($this->is_pinned, ['m' => 1], ['m' => 'off'], ...$extra),
            ],
            'merged_ctl' => [
                'flag' => $this->is_pinned,
                $this->mergeWhen($this->is_pinned, ['m' => 1], ['m' => 'off']),
            ],
            'merged_unless_unpack' => [
                'flag' => $this->is_pinned,
                $this->mergeUnless($this->is_pinned, ['m' => 1], ['m' => 'off'], ...$extra),
            ],
        ];
    }
}
