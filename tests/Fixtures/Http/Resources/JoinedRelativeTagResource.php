<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * A user callback parameter an inline `@var static`, `self` or `parent` on the last statement of an `if` block retypes
 * to the resource, read past that block through members the user has, and one only the post the resource holds has.
 *
 * @mixin Post
 */
final class JoinedRelativeTagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'static_name' => $this->whenLoaded('author', function (User $a) {
                if ($this->is_pinned) {
                    /** @var static $a */
                    $a = $this->opaque($a);
                }

                return $a->name;
            }),
            'self_name' => $this->whenLoaded('author', function (User $a) {
                if ($this->is_pinned) {
                    /** @var self $a */
                    $a = $this->opaque($a);
                }

                return $a->name;
            }),
            'parent_name' => $this->whenLoaded('author', function (User $a) {
                if ($this->is_pinned) {
                    /** @var parent $a */
                    $a = $this->opaque($a);
                }

                return $a->name;
            }),
            'static_key' => $this->whenLoaded('author', function (User $a) {
                if ($this->is_pinned) {
                    /** @var static $a */
                    $a = $this->opaque($a);
                }

                return $a->getKey();
            }),
            'static_title' => $this->whenLoaded('author', function (User $a) {
                if ($this->is_pinned) {
                    /** @var static $a */
                    $a = $this->opaque($a);
                }

                return $a->title;
            }),
        ];
    }

    /** A value no rule types. */
    private function opaque(mixed $value): mixed
    {
        return unserialize(serialize($value));
    }
}
