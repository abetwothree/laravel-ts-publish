<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RuntimeException;
use Workbench\App\Models\Post;

/**
 * Both halves of the guard-body rule, read through analyzeThisMethodSpread()'s branch sweep. `author` is a User,
 * which has no `title`, so a narrowing that reaches the guard's own branch shows as a `string` arm; dirty_label drops
 * that untypable branch, as a ternary drops an untypable arm, and keeps the other branch's `number`.
 *
 * @mixin Post
 */
final class NarrowingGuardBodyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->dirtyRows(),
            ...$this->cleanRows(),
        ];
    }

    /**
     * The guard's own body reads $parent, in the branch proving $parent is NOT a Post, so that read is not narrowed.
     *
     * @return array<string, mixed>
     */
    protected function dirtyRows(): array
    {
        $parent = $this->author;

        if (! $parent instanceof Post) {
            return ['dirty_label' => $parent->title];
        }

        return ['dirty_label' => 0];
    }

    /**
     * The guard's body reads nothing and exits by `throw`, so the statements after it do narrow.
     *
     * @return array<string, mixed>
     */
    protected function cleanRows(): array
    {
        $parent = $this->author;

        if (! $parent instanceof Post) {
            throw new RuntimeException('not a post');
        }

        return ['clean_label' => $parent->title];
    }
}
