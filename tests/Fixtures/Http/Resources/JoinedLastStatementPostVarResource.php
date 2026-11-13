<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AccessorTitleUser;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\GetterTitleUser;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\MagicTitleUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A post callback parameter an inline `@var` on the last statement of an `if` block retypes to a user that may answer
 * `title` through an untyped accessor, an untyped getter or an overriding `__get()`, read past that block.
 *
 * @mixin Post
 */
final class JoinedLastStatementPostVarResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'accessor_title' => $this->transform($this->resource, function (Post $p) {
                if ($this->is_pinned) {
                    /** @var AccessorTitleUser $p */
                    $p = $this->opaque(new AccessorTitleUser()->forceFill(['id' => 1]));
                }

                return $p->title;
            }),
            'magic_title' => $this->transform($this->resource, function (Post $p) {
                if ($this->is_pinned) {
                    /** @var MagicTitleUser $p */
                    $p = $this->opaque(new MagicTitleUser()->forceFill(['id' => 2]));
                }

                return $p->title;
            }),
            'getter_title' => $this->transform($this->resource, function (Post $p) {
                if ($this->is_pinned) {
                    /** @var GetterTitleUser $p */
                    $p = $this->opaque(new GetterTitleUser()->forceFill(['id' => 3]));
                }

                return $p->title;
            }),
        ];
    }

    /** A value no rule types. */
    private function opaque(mixed $value): mixed
    {
        return unserialize(serialize($value));
    }
}
