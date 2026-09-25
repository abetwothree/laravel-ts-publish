<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A class-level `#[TsCasts]` adds `extra_tag`, a key the body never has, beside a docblock-filled `_tag` signature. */
#[TsCasts(['extra_tag' => 'number'])]
final class ClassCastTagSignatureResource extends JsonResource
{
    /** Spreads the filled signature. */
    public function toArray(Request $request): array
    {
        return [...$this->docTags()];
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
