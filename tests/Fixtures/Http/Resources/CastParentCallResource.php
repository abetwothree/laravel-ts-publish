<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;

/** A method `#[TsCasts]` on a `toArray()` whose return is a parent call. */
class CastParentCallResource extends StubGadgetResource
{
    /** @return array<string, mixed> */
    #[TsCasts(['extra' => 'string'])]
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }
}
