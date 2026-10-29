<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;

/**
 * A plain read of first()'s model and a parameter declaring a non-null model both throw where no row matches, so the
 * response that is sent always holds the model.
 *
 * @mixin Post
 */
class ForwardedFirstReadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'content' => $this->comments()->first()->content,
            'declared' => $this->comment($this->comments()->first()),
        ];
    }

    /**
     * The comment it is given.
     */
    private function comment(Comment $comment): Comment
    {
        return $comment;
    }
}
