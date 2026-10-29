<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\RenamedTone;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\UiTone;

/**
 * Columns cast to enums with an override, one renamed by `#[TsEnum]`, and an accessor returning one.
 *
 * @property UiTone $tone
 * @property UiTone|null $maybe_tone
 * @property RenamedTone $shade
 * @property-read UiTone $tone_attr
 */
class UiToneHolder extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /** @var list<string> */
    protected $appends = ['tone_attr'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['tone' => UiTone::class, 'maybe_tone' => UiTone::class, 'shade' => RenamedTone::class];
    }

    /**
     * The tone the row does not hold.
     *
     * @return Attribute<UiTone, never>
     */
    protected function toneAttr(): Attribute
    {
        return Attribute::get(fn (): UiTone => $this->tone === UiTone::Bright ? UiTone::Dim : UiTone::Bright);
    }
}
