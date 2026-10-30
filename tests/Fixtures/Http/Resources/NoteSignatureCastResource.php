<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A test-only resource whose method cast types a signature over a key its pattern matches, as a page helper's does. */
class NoteSignatureCastResource extends JsonResource
{
    /** The notes, a key of which the method's cast types through its signature. */
    #[TsCasts(['[key: `${string}_note`]' => 'number'])]
    public function toArray(Request $request): array
    {
        return ['x_note' => 'y', 'n' => 1];
    }
}
