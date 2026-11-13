<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\BracketedBag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Calls a bag's methods through a resource built around it, each returning a bracketed or conditional type.
 *
 * @extends JsonResource<BracketedBag>
 */
class BracketedReadsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'ids' => BracketedBagResource::make($this->resource)->mixedIds(),
            'one' => BracketedBagResource::make($this->resource)->maybeOne(),
            'maker' => BracketedBagResource::make($this->resource)->maker(),
            'proven' => BracketedBagResource::make($this->resource)->proven(),
            'opaque' => BracketedBagResource::make($this->resource)->opaque(),
            'answer' => BracketedBagResource::make($this->resource)->answer(),
            'guessed' => BracketedBagResource::make($this->resource)->guessed(),
        ];
    }
}
