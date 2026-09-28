<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\PostResource;

/** One shape per method, each putting a docblock-filled index signature beside a key it could conflict with. */
final class IndexSignatureConflictResource extends JsonResource
{
    /** A later number replaces the resource-typed `main_tag`, so only the number is published. */
    public function staleOverride(): array
    {
        return [...$this->literalTags(), ...$this->resourceTag(), 'main_tag' => 5];
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

    /** A named `_tag` key cast to a union of string literals, which needs no import. */
    #[TsCasts(['main_tag' => "'x' | 'y'"])]
    public function literalNamedKey(): array
    {
        return [...$this->docTags(), 'main_tag' => 'x'];
    }

    /** A cast on the filled signature makes its type the app's own, beside a cast key that cannot join the union. */
    #[TsCasts(['[key: `${string}_tag`]' => 'string | number', 'main_tag' => 'Money'])]
    public function castSignature(): array
    {
        return [...$this->docTags()];
    }

    /** An `array_merge()` of literals reaches only the array analysis, with no refine after it. */
    public function mergedShape(): array
    {
        return array_merge([...$this->docTags()], ['price_tag' => 5]);
    }

    /** A filled `_a_tag` signature beside a key cast to a string literal whose escaped quote precedes a `null`. */
    #[TsCasts(['state_a_tag' => "'it\\'s | null | x'"])]
    public function escapedLiteralKey(): array
    {
        return [...$this->docNumberedTags(), 'state_a_tag' => 5];
    }

    /** One branch nests the filled keys under `box_tag`, whose shape prints `undefined`; the other has `price_tag`. */
    public function nestedBranches(): array
    {
        if ($this->resource->exists) {
            return [...$this->docTags(), 'box_tag' => [...$this->docTags()]];
        }

        return ['price_tag' => 5];
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
