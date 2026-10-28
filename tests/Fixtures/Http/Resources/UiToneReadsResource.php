<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\RenamedTone;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\UiToneHolder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\UiTone;

/**
 * Reads of enums with an override that publish the enum's own names: an `EnumResource` of a case, a cast column, an
 * accessor or a renamed enum, and a case's `->value` and `->name`, beside the override a case or a cast column takes.
 *
 * @mixin UiToneHolder
 */
class UiToneReadsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'tone' => UiTone::Dim,
            'column' => $this->tone,
            'from' => EnumResource::make(UiTone::from('dim')),
            'column_resource' => EnumResource::make($this->tone),
            'maybe_resource' => EnumResource::make($this->maybe_tone),
            'accessor_resource' => EnumResource::make($this->tone_attr),
            'shade_resource' => EnumResource::make(RenamedTone::Light),
            'shade_column_resource' => EnumResource::make($this->shade),
            'either' => $request->boolean('wrap') ? EnumResource::make(UiTone::Bright) : UiTone::Dim,
            'value' => UiTone::Bright->value,
            'name' => $this->tone->name,
            'shade_name' => RenamedTone::Dark->name,
            'default_value' => $this->when($request->boolean('value'), fn ($tone = UiTone::Dim) => $tone->value),
        ];
    }
}
