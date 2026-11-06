<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ListingShelf;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Picks at random off a shelf's collections, directly and through a resource built around the shelf.
 *
 * @extends JsonResource<ListingShelf>
 */
class ListingShelfReadsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $shelf = ListingShelfResource::make($this->resource);

        return [
            'comment' => $this->resource->comments()->random(),
            'built_comments' => $shelf->comments(),
            'built_comment' => $shelf->comments()->random(),
            'built_comment_pair' => $shelf->comments()->random(2),
            'built_note' => $shelf->notes()->random(),
            'built_note_pair' => $shelf->notes()->random(2),
            'built_abstract' => $shelf->abstracts()->random(),
            'built_words' => $shelf->words(),
            'built_word' => $shelf->words()->random(),
            'built_word_pair' => $shelf->words()->random(2),
            'built_row' => $shelf->plain()->random(),
            'built_row_pair' => $shelf->plain()->random(2),
        ];
    }
}
