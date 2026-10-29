<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\MappedLevel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\MappedRank;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\MappedLevelHolder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Reads of enums a test keys in `custom_ts_mappings`: a case and a cast column take the mapping, while an
 * `EnumResource` and `->value` keep the enum's own names, a `#[TsEnum]` rename included.
 *
 * @mixin MappedLevelHolder
 */
class MappedLevelReadsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'level' => MappedLevel::Low,
            'column' => $this->level,
            'case_resource' => EnumResource::make(MappedLevel::Low),
            'from_resource' => EnumResource::make(MappedLevel::from('high')),
            'column_resource' => EnumResource::make($this->level),
            'maybe_resource' => EnumResource::make($request->boolean('none') ? null : MappedLevel::High),
            'value' => MappedLevel::Low->value,
            'rank_value' => MappedRank::Top->value,
            'rank_resource' => EnumResource::make(MappedRank::Top),
            'rank_column_resource' => EnumResource::make($this->rank),
        ];
    }
}
