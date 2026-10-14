<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Helpers whose declarations are vague or missing, each typed from what its own returns hold: a scalar, a list
 * literal, list pipelines, an attribute read and returned variables.
 *
 * @mixin Post
 */
final class PostHelperBodyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'shout_untyped' => $this->shoutUntyped($this->title),
            'stages' => $this->stages(),
            'history' => $this->history(),
            'ids' => $this->ids(),
            'untyped_title' => $this->untypedTitle(),
            'closure_first' => $this->closureFirst(),
            'mixed_meta' => $this->mixedMeta(),
        ];
    }

    /** The title in capitals, with no declaration. */
    private function shoutUntyped($text)
    {
        return strtoupper($text);
    }

    /** A list literal behind a vague array. */
    private function stages(): array
    {
        return ['draft', 'review'];
    }

    /** Each comment's content, behind a vague array. */
    private function history(): array
    {
        return $this->comments->map(fn ($comment) => $comment->content)->values()->all();
    }

    /** Each comment's id, behind a vague array. */
    private function ids(): array
    {
        return $this->comments->pluck('id')->all();
    }

    /** The title, with no declaration. */
    private function untypedTitle()
    {
        return $this->title;
    }

    /** A variable built after a closure's own return. */
    private function closureFirst(): array
    {
        $active = $this->comments->filter(function ($comment) {
            return $comment->id > 1;
        });
        $meta = ['title' => $this->title, 'active' => $active->count()];

        return $meta;
    }

    /** A literal return beside a variable one. */
    private function mixedMeta(): array
    {
        if ($this->title === '') {
            return ['a' => 1];
        }

        $meta = ['a' => 2, 'b' => 'x'];

        return $meta;
    }
}
