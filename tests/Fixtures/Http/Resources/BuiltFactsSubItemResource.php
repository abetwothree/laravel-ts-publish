<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;

/** A test-only subclass of BuiltFactsItemResource sending `s` in its place. */
class BuiltFactsSubItemResource extends BuiltFactsItemResource
{
    /** @return array<string, string> */
    public function toArray(Request $request): array
    {
        return ['s' => 'y'];
    }
}
