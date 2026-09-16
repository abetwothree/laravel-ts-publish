<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Models\Concerns\DerivesReleaseVersion;

/** Accessors typed only by their getter bodies: literal shapes, helpers, collection pipelines, cycles. */
class Release extends Model
{
    use DerivesReleaseVersion;

    public const int CHANNEL_STABLE = 1;

    public const int CHANNEL_BETA = 2;

    protected $appends = ['summary'];

    protected $allowedChannels = ['stable', 'beta'];

    /** The labels keyed by channel constant, as a select would want them. */
    public static function channelLabels(): array
    {
        return [self::CHANNEL_STABLE => 'Stable', self::CHANNEL_BETA => 'Beta'];
    }

    /** Old-style accessor with a vague signature and a literal body. */
    public function getSummaryAttribute(): array
    {
        return ['major' => $this->major];
    }

    protected function casts(): array
    {
        return ['major' => 'integer', 'minor' => 'integer'];
    }

    protected function versionData(): Attribute
    {
        return Attribute::get(fn () => ['major' => $this->major, 'minor' => $this->minor]);
    }

    protected function label(): Attribute
    {
        return Attribute::get(function () {
            $prefix = 'v';

            return ['full' => $prefix.$this->major, 'notes' => $this->notes];
        });
    }

    protected function tagList(): Attribute
    {
        return Attribute::get(fn (): array => collect(explode(',', $this->tags_csv))->map(fn ($tag) => ['name' => $tag])->values()->all());
    }

    protected function channelOptions(): Attribute
    {
        return Attribute::get(fn (): array => static::channelLabels());
    }

    protected function channels(): Attribute
    {
        return Attribute::get(fn (): array => $this->allowedChannels);
    }

    /** An empty literal carries no element information, so the `: array` signature answers instead. */
    protected function emptyList(): Attribute
    {
        return Attribute::get(fn (): array => []);
    }

    /** The `new Attribute(get: ...)` form, which getterClosure() reads like make()/get(). */
    protected function constructedVersion(): Attribute
    {
        return new Attribute(get: fn (): array => ['major' => $this->major]);
    }

    protected function loopA(): Attribute
    {
        return Attribute::get(fn () => $this->loop_b);
    }

    protected function loopB(): Attribute
    {
        return Attribute::get(fn () => $this->loop_a);
    }

    /** Loop-built dynamic keys: must stay unknown[], nothing here is statically knowable. */
    protected function dynamicTotals(): Attribute
    {
        return Attribute::get(function (): array {
            $totals = [];

            foreach ([1, 2] as $index) {
                $totals['k'.$index] = $index;
            }

            return $totals;
        });
    }
}
