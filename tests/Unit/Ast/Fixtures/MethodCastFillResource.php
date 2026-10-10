<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Post;

/**
 * A docblock-filled signature beside a resource-typed same-pattern key the method's own cast retypes, so only the
 * publisher, once it fits the key's import channels to the cast, can join the fill.
 *
 * @mixin Post
 */
final class MethodCastFillResource extends JsonResource
{
    /** Spreads the filled signature beside the resource-typed key. */
    #[TsCasts(['main_tag' => 'number'])]
    public function toArray(Request $request): array
    {
        return [...$this->docTags(), 'main_tag' => new PostResource($this->resource)];
    }

    /**
     * `_tag` keys only the docblock types.
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

    /** Deliberately untyped. */
    protected function opaque()
    {
        return $this->resource->getAttribute('title');
    }
}
