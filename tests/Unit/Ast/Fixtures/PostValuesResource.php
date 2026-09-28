<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A trailing values() or all() on a keyed collection, and on a class that declares its own. */
final class PostValuesResource extends JsonResource
{
    /**
     * Each trailing call, on the collection and on the settings.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $scores = new PostScores;

        return [
            'values' => $scores->byName()->values(),
            'all' => $scores->byName()->all(),
            'values_all' => $scores->byName()->values()->all(),
            'settings_values' => $scores->settings()->values(),
            'settings_all' => $scores->settings()->all(),
        ];
    }
}
