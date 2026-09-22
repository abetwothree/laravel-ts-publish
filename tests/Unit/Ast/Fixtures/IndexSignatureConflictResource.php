<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\PostResource;

/** One shape per method, each putting a docblock-filled index signature beside a key it could conflict with. */
final class IndexSignatureConflictResource extends JsonResource
{
    /** A later literal replaces the resource-typed `main_tag`, so only the literal is published. */
    public function staleOverride(): array
    {
        return [...$this->literalTags(), ...$this->resourceTag(), 'main_tag' => 'x'];
    }

    /** A filled `_tag` signature beside a `_tag` key nothing types. */
    public function declinedKey(): array
    {
        return [...$this->docTags(), ...$this->opaqueTag()];
    }

    /** A filled `_tag` signature beside a resource-typed `_tag` key. */
    public function declinedResourceKey(): array
    {
        return [...$this->docTags(), ...$this->resourceTag()];
    }

    /** Two filled signatures whose patterns overlap: every `_a_tag` key is also a `_tag` key. */
    public function overlappingPatterns(): array
    {
        return [...$this->docTags(), ...$this->docNumberedTags()];
    }

    /**
     * Only this method's own docblock types the spread-in signature, after the merge.
     *
     * @return array<string, string>
     */
    public function docblockAfterMerge(): array
    {
        return [...$this->untypedTags(), 'price_tag' => 5];
    }

    /** Returns the shape above whole, as a spread method. */
    public function directSpread(): array
    {
        return $this->docblockAfterMerge();
    }

    /** The method's own `#[TsCasts]` retypes the named `_tag` key after the merge. */
    #[TsCasts(['main_tag' => 'number'])]
    public function castNamedKey(): array
    {
        return [...$this->docTags(), 'main_tag' => 'x'];
    }

    /** Body-typed `_tag` keys. */
    public function literalTags(): array
    {
        $data = [];

        foreach (['east', 'west'] as $name) {
            $data["{$name}_tag"] = 'Tag';
        }

        return $data;
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

    /**
     * `_a_tag` keys only the docblock types.
     *
     * @return array<string, int>
     */
    public function docNumberedTags(): array
    {
        $data = [];

        foreach (['east', 'west'] as $name) {
            $data["{$name}_a_tag"] = $this->opaque();
        }

        return $data;
    }

    /** `_tag` keys nothing types. */
    public function untypedTags(): array
    {
        $data = [];

        foreach (['east', 'west'] as $name) {
            $data["{$name}_tag"] = $this->opaque();
        }

        return $data;
    }

    /** A resource-typed named `_tag` key. */
    public function resourceTag(): array
    {
        return ['main_tag' => new PostResource($this->resource)];
    }

    /** A named `_tag` key nothing types. */
    public function opaqueTag(): array
    {
        return ['main_tag' => $this->opaque()];
    }

    /** Deliberately untyped. */
    protected function opaque()
    {
        return $this->resource->getAttribute('title');
    }
}
