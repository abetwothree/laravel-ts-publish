<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * Locals holding the author that a branch replaces with a string literal, a string column or a value no rule types,
 * read past the branch through a member the author has and one it lacks.
 *
 * @mixin Post
 */
final class JoinedLeftOutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $guest = $this->author;

        if ($this->is_pinned) {
            $guest = 'guest';
        }

        $titled = $this->author;

        if ($this->is_pinned) {
            $titled = $this->title;
        }

        $opaque = $this->author;

        if ($this->is_pinned) {
            $opaque = $this->opaque($this->author);
        }

        return [
            'guest_name' => $guest->name,
            'titled_name' => $titled->name,
            'opaque_title' => $opaque->title,
        ];
    }

    /** A value no rule types. */
    private function opaque(mixed $value): mixed
    {
        return unserialize(serialize($value));
    }
}
