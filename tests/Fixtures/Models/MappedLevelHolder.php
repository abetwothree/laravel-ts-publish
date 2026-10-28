<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\MappedLevel;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Enums\MappedRank;
use Illuminate\Database\Eloquent\Model;

/**
 * Columns cast to enums a test keys in `custom_ts_mappings`, one renamed by `#[TsEnum]`.
 *
 * @property MappedLevel $level
 * @property MappedRank $rank
 */
class MappedLevelHolder extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['level' => MappedLevel::class, 'rank' => MappedRank::class];
    }
}
