<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\LabelSerializeComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Filters its comment's post with only() and except(), which read each attribute through getAttribute(), so a
 * serializing cast sends get()'s value.
 *
 * @mixin LabelSerializeComment
 */
final class LabelSerializeFilterResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'rel_only' => $this->labelPost->only(['title', 'user_id']),
            'rel_except' => $this->labelPost->except([
                'id', 'content', 'status', 'published_at', 'metadata', 'rating', 'category', 'options', 'deleted_at',
                'created_at', 'updated_at', 'category_id', 'visibility', 'priority', 'word_count', 'reading_time_minutes',
                'featured_image_url', 'is_pinned',
            ]),
            'rel_plain_only' => $this->labelPost->only(['id']),
        ];
    }
}
