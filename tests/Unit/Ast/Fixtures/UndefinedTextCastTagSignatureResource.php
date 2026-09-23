<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Its `_tag` casts name `undefined` only inside a string literal and a `Record`, beside an optional `price_tag`. */
#[TsCasts(['state_tag' => "'undefined' | 'defined'", 'extra_tag' => 'Record<string, number | undefined>'])]
final class UndefinedTextCastTagSignatureResource extends JsonResource
{
    /** Two branches, so `price_tag` publishes optional beside the signature. */
    public function toArray(Request $request): array
    {
        if ($request->has('tagged')) {
            return [...$this->docTags(), 'id' => 1];
        }

        return ['price_tag' => 5, 'id' => 2];
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
