<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\DraftPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;

/**
 * Reads each of a user's posts past a guard on the loop's own value variable that continues for any post but a
 * DraftPost, and under a positive test for one. DraftPost casts `word_count` to a string, where Post casts an integer.
 *
 * @mixin User
 */
class GuardedLoopPostsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [];

        foreach ($this->posts as $post) {
            if (! $post instanceof DraftPost) {
                continue;
            }

            $data['guarded_word_count'] = $post->word_count;
        }

        foreach ($this->posts as $draft) {
            if ($draft instanceof DraftPost) {
                $data['positive_word_count'] = $draft->word_count;
            }
        }

        return $data;
    }
}
