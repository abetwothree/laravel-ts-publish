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
 * accessor, a renamed enum, a coalesce, a constant, a helper, a closure default, a list, a `match` or a collection,
 * and a case's `->value` and `->name`, beside the override a case or a cast column takes.
 *
 * @mixin UiToneHolder
 */
class UiToneReadsResource extends JsonResource
{
    public const UiTone DEFAULT = UiTone::Dim;

    public const array TONES = [UiTone::Bright, UiTone::Dim];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tones = [UiTone::Dim, UiTone::Bright];

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
            'coalesce_resource' => EnumResource::make($this->maybe_tone ?? UiTone::Dim),
            'constant_resource' => EnumResource::make(self::DEFAULT),
            'helper_resource' => EnumResource::make($this->picked()),
            'default_resource' => $this->when($request->boolean('value'), fn ($tone = UiTone::Dim) => EnumResource::make($tone)),
            'constant_list' => EnumResource::collection(self::TONES),
            'literal_list' => EnumResource::collection([UiTone::Bright, UiTone::Dim]),
            'local_list' => EnumResource::collection($tones),
            'matched_resource' => EnumResource::make(match ($request->integer('pick')) {
                1 => UiTone::Bright,
                default => UiTone::Dim,
            }),
            'untyped_resource' => EnumResource::make($this->untypedPick()),
            'values_list' => EnumResource::collection(array_values(self::TONES)),
            'collected_list' => EnumResource::collection(collect([UiTone::Dim])),
            'either_list' => EnumResource::collection($request->boolean('wrap') ? self::TONES : []),
        ];
    }

    /**
     * A tone a helper returns with no declared type.
     */
    protected function untypedPick()
    {
        return UiTone::Bright;
    }

    /**
     * The tone a helper's declared return names.
     */
    protected function picked(): UiTone
    {
        return UiTone::Bright;
    }
}
