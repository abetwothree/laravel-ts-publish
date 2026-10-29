<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\Concerns\SummarizesPosts;
use Workbench\App\Models\Post;

/**
 * Helpers whose typed parameters their bodies read, through every reading of a helper body: a spread, a value, a
 * nested call, a static helper and a trait's.
 *
 * @mixin Post
 */
final class PostHelperParamsResource extends JsonResource
{
    use SummarizesPosts;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->summary($this->resource),
            'summary' => $this->summary($this->resource),
            'card' => $this->card($this->resource),
            'nested' => $this->nested($this->resource),
            'static_card' => self::staticCard($this->resource),
            ...$this->headline($this->resource),
            'maybe' => $this->maybe($this->resource),
            'status_label' => $this->statusLabel($this->status),
            'status_payload' => $this->statusPayload($this->status),
            ...$this->statusPayload($this->status),
        ];
    }

    /**
     * The post's identity from a static helper.
     *
     * @return array<string, mixed>
     */
    private static function staticCard(Post $post): array
    {
        return ['static_id' => $post->id];
    }

    /**
     * The post's identity, spread from its own only().
     *
     * @return array<string, mixed>
     */
    private function summary(Post $post): array
    {
        return [...$post->only(['id', 'title']), 'extra' => 1];
    }

    /**
     * The post's identity, read field by field.
     *
     * @return array<string, mixed>
     */
    private function card(Post $post): array
    {
        return ['card_id' => $post->id, 'card_title' => $post->title];
    }

    /**
     * Another helper's card, passed the same post.
     *
     * @return array<string, mixed>
     */
    private function nested(Post $post): array
    {
        return ['inner' => $this->card($post)];
    }

    /**
     * A title that a missing post leaves null.
     *
     * @return array<string, mixed>
     */
    private function maybe(?Post $post): array
    {
        return ['maybe_title' => $post?->title];
    }

    /**
     * The status's name, read off an enum parameter.
     *
     * @return array<string, mixed>
     */
    private function statusLabel(Status $status): array
    {
        return ['label' => $status->name, 'value' => $status->value];
    }

    /**
     * The status itself, which a value read cannot import, beside its name.
     *
     * @return array<string, mixed>
     */
    private function statusPayload(Status $status): array
    {
        return ['payload_status' => $status, 'payload_name' => $status->name];
    }
}
