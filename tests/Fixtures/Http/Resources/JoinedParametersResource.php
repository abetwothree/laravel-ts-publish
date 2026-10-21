<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * transform() callback parameters a write inside a block may replace, read past that block: passed the title, a
 * property no rule types or a call no rule types.
 *
 * @mixin Post
 */
final class JoinedParametersResource extends JsonResource
{
    /** A value no rule types. */
    public mixed $vague = 5;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'joined' => $this->transform($this->title, function ($title) {
                foreach ($this->comments as $comment) {
                    if ($comment->content !== '') {
                        $title = $comment->id;

                        continue;
                    }
                }

                return $title;
            }),
            'vague_joined' => $this->transform($this->vague, function ($value) {
                foreach ($this->comments as $comment) {
                    if ($comment->content !== '') {
                        $value = $comment->id;

                        continue;
                    }
                }

                return $value;
            }),
            'opaque_joined' => $this->transform($this->opaque(), function ($value) {
                foreach ($this->comments as $comment) {
                    if ($comment->content !== '') {
                        $value = $comment->id;

                        continue;
                    }
                }

                return $value;
            }),
        ];
    }

    /**
     * A value no rule types.
     */
    public function opaque(): mixed
    {
        return json_decode('"s"');
    }
}
