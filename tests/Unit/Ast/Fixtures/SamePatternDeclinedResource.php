<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\PostResource;

/** Same-pattern keys the index-signature union declines: one names a resource, one is untyped. */
final class SamePatternDeclinedResource extends JsonResource
{
    /** Spreads each signature beside the named key it matches. */
    public function toArray(Request $request): array
    {
        return [...$this->literalTags(), ...$this->resourceTag(), ...$this->literalNotes(), ...$this->opaqueNote()];
    }

    /** A body-typed `_tag` signature. */
    public function literalTags(): array
    {
        $data = [];

        foreach (['east', 'west'] as $name) {
            $data["{$name}_tag"] = 'Tag';
        }

        return $data;
    }

    /** A named `_tag` key whose type the transformer rewrites under its own name. */
    public function resourceTag(): array
    {
        return ['main_tag' => new PostResource($this->resource)];
    }

    /** A body-typed `_note` signature. */
    public function literalNotes(): array
    {
        $data = [];

        foreach (['east', 'west'] as $name) {
            $data["{$name}_note"] = 'Note';
        }

        return $data;
    }

    /** A named `_note` key nothing types. */
    public function opaqueNote(): array
    {
        return ['main_note' => $this->opaque()];
    }

    /** Deliberately untyped. */
    protected function opaque()
    {
        return $this->resource->getAttribute('title');
    }
}
