<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/** The collection `Ratchet` guesses: it names no API resource and sends keys beside the list in its own `toArray()`. */
class RatchetCollection extends ResourceCollection
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['data' => $this->collection, 'count' => $this->count()];
    }
}
