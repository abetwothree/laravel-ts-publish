<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource returning keys a spread or a later key writes again on one path, and none of them on the other.
 *
 * @mixin Post
 */
final class SpreadReturnsJoinResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $titled = ['by' => $this->title];

        if ($request->boolean('titled')) {
            return [
                'by' => $this->author,
                ...$titled,
                'note' => $this->author,
                ...['note' => $this->title],
                ...$this->authoredAt(),
                'at' => $this->title,
            ];
        }

        return ['id' => $this->id];
    }

    /** @return array<string, mixed> */
    private function authoredAt(): array
    {
        return ['at' => $this->author];
    }
}
