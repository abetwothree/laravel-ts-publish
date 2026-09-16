<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RuntimeException;
use Workbench\App\Models\Post;

/**
 * Both halves of the guard-body rule, read through analyzeThisMethodSpread()'s first-Return_ path.
 *
 * `author` is a User, and User has no `title`, so a narrowing that reaches the wrong branch is
 * visible as `string` where the honest answer is `unknown`.
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
     * The guard's own body reads $parent — the branch proving $parent is NOT a Post — so nothing binds.
     *
     * @return array<string, mixed>
     */
    protected function dirtyRows(): array
    {
        $parent = $this->author;

        if (! $parent instanceof Post) {
            return ['dirty_label' => $parent->title];
        }

        return ['dirty_label' => 'ok'];
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
