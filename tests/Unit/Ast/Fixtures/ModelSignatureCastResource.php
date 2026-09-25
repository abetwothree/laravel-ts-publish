<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Backslash signatures its model casts under both spellings.
 *
 * @mixin SignatureCastModel
 */
final class ModelSignatureCastResource extends JsonResource
{
    /** Spreads the signatures below. */
    public function toArray(Request $request): array
    {
        return [...$this->keys()];
    }

    /** Keys whose literal text holds a backslash. */
    public function keys(): array
    {
        $data = [];

        foreach (['a', 'b'] as $name) {
            $data["{$name}\\_v"] = 'x';
            $data["{$name}\\_w"] = 'x';
        }

        return $data;
    }
}
