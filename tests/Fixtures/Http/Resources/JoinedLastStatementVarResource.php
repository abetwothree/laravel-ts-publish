<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * Callback parameters an inline `@var` retypes on the last statement of an `if` block, read past that block: as a
 * value, and as a member that only the old model or only the tag's model has.
 *
 * @mixin Post
 */
final class JoinedLastStatementVarResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'param_last' => $this->transform($this->title, function ($c) {
                if ($this->is_pinned) {
                    /** @var int $c */
                    $c = $this->opaque(5);
                }

                return $c;
            }),
            'param_not_last' => $this->transform($this->title, function ($c) {
                if ($this->is_pinned) {
                    /** @var int $c */
                    $c = $this->opaque(5);
                    $after = 1;
                }

                return $c;
            }),
            'param_last_class' => $this->whenLoaded('author', function ($a) {
                if ($this->is_pinned) {
                    /** @var Comment $a */
                    $a = $this->opaque($this->comments->first());
                }

                return $a->content ?? null;
            }),
            'name_last' => $this->whenLoaded('author', function (User $a) {
                if ($this->is_pinned) {
                    /** @var Comment $a */
                    $a = $this->opaque($this->comments->first());
                }

                return $a->name;
            }),
            'name_last_untyped' => $this->whenLoaded('author', function ($a) {
                if ($this->is_pinned) {
                    /** @var Comment $a */
                    $a = $this->opaque($this->comments->first());
                }

                return $a->name;
            }),
        ];
    }

    /** A value no rule types. */
    private function opaque(mixed $value): mixed
    {
        return unserialize(serialize($value));
    }
}
