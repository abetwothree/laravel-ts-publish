<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A docblock-filled signature beside a same-pattern key only the class-level cast types, so the fill joins the cast.
 *
 * @mixin Post
 */
#[TsCasts(['main_tag' => 'number'])]
final class CastRestoredFillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [...$this->docTags(), ...$this->opaqueTag()];
    }

    /**
     * Only the docblock types these values.
     *
     * @return array<string, string>
     */
    public function docTags(): array
    {
        $data = [];

        foreach (['east', 'west'] as $name) {
            $data["{$name}_tag"] = $this->opaque();
        }

        return $data;
    }

    /** A named key the `_tag` pattern also matches, which nothing but the cast types. */
    public function opaqueTag(): array
    {
        return ['main_tag' => $this->opaque()];
    }

    /** Deliberately untyped so only a docblock or a cast can type what it returns. */
    protected function opaque()
    {
        return $this->resource->getAttribute('title');
    }
}
