<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A docblock-filled `_tag` signature on an interface that extends one whose keys the package cannot see. */
#[TsExtends('HasPriceTag')]
final class ExtendedTagSignatureResource extends JsonResource
{
    /** Spreads the filled signature. */
    public function toArray(Request $request): array
    {
        return [...$this->docTags(), 'id' => 1];
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
