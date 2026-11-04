<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;

/** array_map() over lists of comments, one holding nulls, and of counts: the only keys naming the comment model. */
final class ZippedModelsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'zipped' => array_map(null, $this->comments(), $this->counts()),
            'firsts' => array_map(fn ($comment, $count) => $comment, $this->comments(), $this->counts()),
            'maybe' => array_map(fn ($comment) => $comment, $this->maybeComments()),
        ];
    }

    /** @return list<Comment> */
    public function comments(): array
    {
        return [new Comment(['content' => 'first'])];
    }

    /** @return list<Comment|null> */
    public function maybeComments(): array
    {
        return [new Comment(['content' => 'first']), null];
    }

    /** @return list<int> */
    public function counts(): array
    {
        return [1, 2];
    }
}
