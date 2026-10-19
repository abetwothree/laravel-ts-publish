<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\LabeledPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Models a variable holds: a helper parameter declaring the post, whose untyped label() the resource also calls on its
 * own row, and a new post saved before its key is read.
 *
 * @mixin LabeledPost
 */
final class VariableHeldModelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'resource_label' => $this->resource->label(),
            'param_label' => $this->labelOf($this->resource),
        ];
    }

    /**
     * A key read after save(), which the package does not follow.
     *
     * @return array<string, mixed>
     */
    public function savedDraft(): array
    {
        $draft = new Post;
        $draft->save();

        return ['saved_draft_key' => $draft->getKey()];
    }

    /** The label of the post the call passes. */
    private function labelOf(LabeledPost $post)
    {
        return $post->label();
    }
}
