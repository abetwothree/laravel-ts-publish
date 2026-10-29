<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RuntimeException;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * Parameters a write inside a block replaces, read in that block or past it: transform()'s passed the title, written in
 * an `if`, its condition, a loop, a `switch` case or a `try`; whenLoaded()'s passed the comments or the author, written
 * a count, a column, another model or a helper's return; a helper's declared string; and an unbound local beside them.
 *
 * @mixin Post
 */
final class InBlockParameterReadsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'inner_param' => $this->transform($this->title, function ($title) {
                $out = 'none';

                if ($this->is_pinned) {
                    $title = $this->id;
                    $out = $title;
                }

                return $out;
            }),
            'inner_param_direct' => $this->transform($this->title, function ($title) {
                if ($this->is_pinned) {
                    $title = $this->id;

                    return ['v' => $title];
                }

                return ['v' => 'x'];
            }),
            'inner_loop' => $this->transform($this->title, function ($title) {
                $ids = 'none';

                foreach ($this->comments as $comment) {
                    $title = $comment->id;
                    $ids = $title;
                }

                return $ids;
            }),
            'loop_before' => $this->transform($this->title, function ($title) {
                $last = 'none';

                foreach ($this->comments as $comment) {
                    $last = $title;
                    $title = $comment->id;
                }

                return $last;
            }),
            'inner_condition' => $this->transform($this->title, function ($title) {
                $out = 'none';

                if (($title = $this->id) > 0) {
                    $out = $title;
                }

                return $out;
            }),
            'inner_case' => $this->transform($this->title, function ($title) {
                $out = 'none';

                switch ($this->is_pinned) {
                    case true:
                        $title = $this->id;
                        // no break
                    case false:
                        $out = $title;
                }

                return $out;
            }),
            'inner_decoded' => $this->transform($this->title, function ($title) {
                $out = 'none';

                if ($this->is_pinned) {
                    $title = json_decode($title);
                    $out = $title;
                }

                return $out;
            }),
            'try_inner' => $this->transform($this->title, function ($title) {
                $out = 'none';

                try {
                    $title = $this->id;
                    $out = $title;
                } catch (RuntimeException) {
                }

                return $out;
            }),
            'inner_local' => $this->transform($this->title, function ($title) {
                $t = 'a';
                $out = 'none';

                if ($this->is_pinned) {
                    $t = $this->id;
                    $out = $t;
                }

                return $out;
            }),
            'walk_key' => $this->transform($this->title, function ($title) {
                $d = ['t' => 'none'];

                if ($this->is_pinned) {
                    $title = $this->id;
                    $d['t'] = $title;
                }

                return $d;
            }),
            'when_loaded_value' => $this->whenLoaded('comments', function ($comments) {
                $n = 'none';

                if ($this->is_pinned) {
                    $comments = $comments->count();
                    $n = $comments;
                }

                return $n;
            }),
            'in_model_column' => $this->whenLoaded('author', function ($author) {
                $n = 'none';

                if ($this->is_pinned) {
                    $author = $author->id;
                    $n = $author;
                }

                return $n;
            }),
            'in_model_kept' => $this->whenLoaded('author', function ($author) {
                $n = 'none';

                if ($this->is_pinned) {
                    $author = $this->same($author);
                    $n = $author;
                }

                return $n;
            }),
            'in_model_ended' => $this->whenLoaded('author', function ($author) {
                $found = null;

                if ($this->is_pinned) {
                    $author = $this->comments->first();
                    $found = $author;
                }

                return $found;
            }),
            'past_ended' => $this->whenLoaded('author', function ($author) {
                if ($this->is_pinned) {
                    $author = $author->profile;
                }

                return $author;
            }),
            'past_ended_member' => $this->whenLoaded('author', function ($author) {
                if ($this->is_pinned) {
                    $author = $author->profile;
                }

                return $author->id;
            }),
            'in_before' => $this->transform($this->title, function ($title) {
                $n = 'none';

                if ($this->is_pinned) {
                    $n = $title;
                    $title = $this->author;
                }

                return $n;
            }),
            'helper_untyped' => $this->helperUntyped($this->title),
        ];
    }

    /**
     * The model it is passed.
     */
    private function same(User $user)
    {
        return $user;
    }

    /**
     * Five for a pinned post, else none.
     */
    private function helperUntyped(string $title)
    {
        $out = 'none';

        if ($this->is_pinned) {
            $title = 5;
            $out = $title;
        }

        return $out;
    }
}
